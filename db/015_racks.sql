-- 015_racks.sql — numbered racks inside a storage area.
-- A rack is a location whose parent_location_id names its area (Packaged goods, Cold room…).
-- Because every ledger table already points at app.locations, racks take part in balances,
-- transfers, counts, adjustments, packaging output and removals with no other change.
-- A rack inherits premises, kind and tax state from its area, so kind-based pickers
-- (removals from packaged goods, packaging output) offer racks, and TTB tax-state totals
-- are unchanged. One level only: a rack cannot hold racks. A rack can hold many lots.
SET search_path = app, public;

ALTER TABLE app.locations
    ADD COLUMN parent_location_id bigint REFERENCES app.locations(id),
    ADD COLUMN rack_number text,
    ADD CONSTRAINT locations_rack_shape CHECK ((parent_location_id IS NULL) = (rack_number IS NULL)),
    ADD CONSTRAINT locations_rack_number_check CHECK (rack_number IS NULL OR rack_number ~ '^[A-Za-z0-9-]{1,12}$'),
    ADD CONSTRAINT locations_rack_not_own_area CHECK (parent_location_id <> id);

CREATE UNIQUE INDEX locations_premises_rack_number_key ON app.locations (premises_id, upper(rack_number)) WHERE rack_number IS NOT NULL;
CREATE INDEX locations_parent_idx ON app.locations (parent_location_id) WHERE parent_location_id IS NOT NULL;

-- Sort key that puts rack 2 before rack 10 and A2 before A10.
CREATE OR REPLACE FUNCTION app.rack_sort_key(p_rack_number text) RETURNS text
LANGUAGE plpgsql IMMUTABLE AS $$
DECLARE
    v_out text := '';
    v_part text;
BEGIN
    IF p_rack_number IS NULL THEN
        RETURN NULL;
    END IF;
    FOR v_part IN SELECT (regexp_matches(upper(p_rack_number), '([0-9]+|[^0-9]+)', 'g'))[1] LOOP
        v_out := v_out || CASE WHEN v_part ~ '^[0-9]+$' THEN lpad(v_part, 10, '0') ELSE v_part END;
    END LOOP;
    RETURN v_out;
END $$;

-- Racks copy premises, kind and tax state from their area and are named "Rack <number>".
CREATE OR REPLACE FUNCTION app.locations_rack_rules() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE
    v_area app.locations%ROWTYPE;
BEGIN
    IF NEW.parent_location_id IS NULL THEN
        RETURN NEW;
    END IF;
    SELECT * INTO v_area FROM app.locations WHERE id = NEW.parent_location_id;
    IF NOT FOUND THEN
        RAISE EXCEPTION 'The area for this rack does not exist.' USING ERRCODE = 'foreign_key_violation';
    END IF;
    IF v_area.parent_location_id IS NOT NULL THEN
        RAISE EXCEPTION 'A rack must sit in an area, not in another rack.' USING ERRCODE = 'check_violation';
    END IF;
    IF TG_OP = 'UPDATE' AND EXISTS (SELECT 1 FROM app.locations WHERE parent_location_id = NEW.id) THEN
        RAISE EXCEPTION 'This area has racks, so it cannot become a rack.' USING ERRCODE = 'check_violation';
    END IF;
    NEW.rack_number := upper(NEW.rack_number);
    NEW.premises_id := v_area.premises_id;
    NEW.kind        := v_area.kind;
    NEW.tax_state   := v_area.tax_state;
    NEW.name        := 'Rack ' || NEW.rack_number;
    RETURN NEW;
END $$;

CREATE TRIGGER locations_rack_rules BEFORE INSERT OR UPDATE ON app.locations
    FOR EACH ROW EXECUTE FUNCTION app.locations_rack_rules();

-- When an area changes premises, kind or tax state its racks follow. The tax-state
-- interlock in the application refuses the change while the area or a rack holds stock.
CREATE OR REPLACE FUNCTION app.locations_area_cascade() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    UPDATE app.locations SET premises_id = NEW.premises_id, kind = NEW.kind, tax_state = NEW.tax_state
     WHERE parent_location_id = NEW.id;
    RETURN NULL;
END $$;

CREATE TRIGGER locations_area_cascade AFTER UPDATE OF premises_id, kind, tax_state ON app.locations
    FOR EACH ROW WHEN (NEW.parent_location_id IS NULL
                       AND (OLD.premises_id, OLD.kind, OLD.tax_state) IS DISTINCT FROM (NEW.premises_id, NEW.kind, NEW.tax_state))
    EXECUTE FUNCTION app.locations_area_cascade();

-- Balances gain the area and rack (new columns go last so dependent views keep working).
CREATE OR REPLACE VIEW app.v_lot_balances AS
SELECT b.item_id, i.code AS item_code, i.name AS item_name, i.item_class, i.base_unit_code,
       b.lot_id, l.lot_number, l.quality_status, l.expires_on, l.received_on, l.unit_cost_base,
       b.location_id, loc.name AS location_name, loc.tax_state, loc.premises_id,
       b.qty_on_hand, b.qty_allocated, (b.qty_on_hand - b.qty_allocated) AS qty_available,
       (b.qty_on_hand * l.unit_cost_base) AS value_at_lot_cost,
       COALESCE(loc.parent_location_id, loc.id) AS area_location_id,
       COALESCE(area.name, loc.name) AS area_name,
       loc.rack_number,
       l.produced_on
  FROM app.inventory_balances b
  JOIN app.items i ON i.id = b.item_id
  JOIN app.lots l ON l.id = b.lot_id
  JOIN app.locations loc ON loc.id = b.location_id
  LEFT JOIN app.locations area ON area.id = loc.parent_location_id
 WHERE b.qty_on_hand <> 0 OR b.qty_allocated <> 0;

CREATE OR REPLACE VIEW app.v_finished_stock AS
SELECT fl.lot_id,
       l.lot_number,
       fl.batch_id,
       b.number AS batch_number,
       p.id AS product_id,
       p.name AS product_name,
       pc.name AS package_name,
       pc.package_kind,
       fl.packaged_on,
       fl.tax_class,
       fl.abv,
       fl.best_before_on,
       vb.location_id,
       vb.location_name,
       vb.tax_state,
       vb.qty_on_hand AS units_on_hand,
       vb.qty_available AS units_available,
       vb.qty_on_hand * fl.unit_volume_l AS volume_on_hand_l,
       fl.unit_cost,
       vb.area_location_id,
       vb.area_name,
       vb.rack_number,
       pc.id AS packaging_configuration_id
  FROM app.finished_lots fl
  JOIN app.lots l ON l.id = fl.lot_id
  JOIN app.batches b ON b.id = fl.batch_id
  JOIN app.products p ON p.id = b.product_id
  JOIN app.packaging_configurations pc ON pc.id = fl.packaging_configuration_id
  JOIN app.v_lot_balances vb ON vb.lot_id = fl.lot_id;

-- Every positive balance with its area, rack, batch and age, for the rack board and
-- FIFO picking. fifo_rank orders the released stock of one item across all racks and
-- areas of a premises, oldest first: rank 1 is the lot to pick next (one lot split
-- over two racks shares its rank). Finished goods age from their packaging date, other
-- lots from production or receipt. Unreleased lots have no rank.
CREATE OR REPLACE VIEW app.v_fifo_stock AS
SELECT vb.premises_id, vb.area_location_id, vb.area_name, vb.location_id, vb.location_name, vb.rack_number,
       app.rack_sort_key(vb.rack_number) AS rack_sort,
       vb.item_id, vb.item_code, vb.item_name, vb.item_class, vb.base_unit_code,
       vb.lot_id, vb.lot_number, vb.quality_status,
       fl.batch_id, bt.number AS batch_number, p.id AS product_id, p.name AS product_name,
       pc.name AS package_name, pc.package_kind,
       COALESCE(fl.packaged_on, vb.produced_on, vb.received_on) AS stock_date,
       COALESCE(fl.best_before_on, vb.expires_on) AS use_by,
       vb.qty_on_hand, vb.qty_allocated, vb.qty_available,
       CASE WHEN vb.quality_status = 'released' THEN
           dense_rank() OVER (PARTITION BY vb.premises_id, vb.item_id, (vb.quality_status = 'released')
                              ORDER BY COALESCE(fl.packaged_on, vb.produced_on, vb.received_on) NULLS LAST, vb.lot_number)
       END AS fifo_rank
  FROM app.v_lot_balances vb
  LEFT JOIN app.finished_lots fl ON fl.lot_id = vb.lot_id
  LEFT JOIN app.batches bt ON bt.id = fl.batch_id
  LEFT JOIN app.products p ON p.id = bt.product_id
  LEFT JOIN app.packaging_configurations pc ON pc.id = fl.packaging_configuration_id
 WHERE vb.qty_on_hand > 0;
