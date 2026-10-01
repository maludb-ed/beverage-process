-- 006_ledger.sql — the inventory ledger, balances, interlocks, transfers,
-- adjustments, counts. Slice 3, but the ledger is posted to from slice 2 on.
SET search_path = app, public;

-- Every movement is one signed row against (item, lot, location). A logical
-- event (transfer, consumption, packaging) writes several rows sharing group_id.
CREATE TABLE app.inventory_transactions (
    id                bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    group_id          uuid NOT NULL DEFAULT gen_random_uuid(),
    txn_type          text NOT NULL CHECK (txn_type IN (
                          'receipt','issue','transfer_out','transfer_in','adjustment','count_correction',
                          'production_output','packaging_output','removal','return','destruction','reversal')),
    item_id           bigint NOT NULL REFERENCES app.items(id),
    lot_id            bigint NOT NULL REFERENCES app.lots(id),
    location_id       bigint NOT NULL REFERENCES app.locations(id),
    premises_id       bigint NOT NULL REFERENCES app.premises(id),
    qty_base          numeric(18,4) NOT NULL CHECK (qty_base <> 0),
    unit_cost_base    numeric(18,6) NOT NULL DEFAULT 0,
    tax_state         text NOT NULL CHECK (tax_state IN ('bonded','tax_paid')),   -- of location_id at posting
    counterparty_kind text NOT NULL DEFAULT 'none' CHECK (counterparty_kind IN (
                          'none','location','batch','press_run','packaging_run','supplier','customer','disposal')),
    counterparty_id   bigint,
    reason_code_id    bigint REFERENCES app.reason_codes(id),
    ttb_category      text NOT NULL DEFAULT 'none' CHECK (ttb_category IN (
                          'none','received','produced','used_in_production','bottled','removed_tax_paid',
                          'removed_in_bond','export','testing','destroyed','breakage','inventory_loss',
                          'casualty_loss','shortage','inventory_gain','returned')),
    reference_kind    text NOT NULL CHECK (reference_kind IN (
                          'goods_receipt','transfer','adjustment','count','press_run','batch_consumption',
                          'batch_output','packaging_run','removal','return','loss_event','co_product_disposition',
                          'yeast_harvest','reversal','opening')),
    reference_id      bigint NOT NULL,
    reverses_id       bigint REFERENCES app.inventory_transactions(id),
    idempotency_key   text NOT NULL UNIQUE,
    occurred_at       timestamptz NOT NULL,
    posted_at         timestamptz NOT NULL DEFAULT now(),
    actor_id          bigint REFERENCES app.users(id),
    note              text
);
CREATE INDEX inventory_transactions_lot_idx      ON app.inventory_transactions (lot_id, occurred_at);
CREATE INDEX inventory_transactions_item_idx     ON app.inventory_transactions (item_id, occurred_at);
CREATE INDEX inventory_transactions_location_idx ON app.inventory_transactions (location_id, occurred_at);
CREATE INDEX inventory_transactions_ref_idx      ON app.inventory_transactions (reference_kind, reference_id);
CREATE INDEX inventory_transactions_group_idx    ON app.inventory_transactions (group_id);
CREATE INDEX inventory_transactions_period_idx   ON app.inventory_transactions (premises_id, occurred_at, ttb_category);
CREATE TRIGGER inventory_transactions_immutable BEFORE UPDATE OR DELETE ON app.inventory_transactions
    FOR EACH ROW EXECUTE FUNCTION app.forbid_change();

-- Materialized balances, maintained by trigger, recomputable --------------------------
CREATE TABLE app.inventory_balances (
    item_id       bigint NOT NULL REFERENCES app.items(id),
    lot_id        bigint NOT NULL REFERENCES app.lots(id),
    location_id   bigint NOT NULL REFERENCES app.locations(id),
    qty_on_hand   numeric(18,4) NOT NULL DEFAULT 0,
    qty_allocated numeric(18,4) NOT NULL DEFAULT 0,
    updated_at    timestamptz NOT NULL DEFAULT now(),
    PRIMARY KEY (item_id, lot_id, location_id)
);
CREATE INDEX inventory_balances_location_idx ON app.inventory_balances (location_id) WHERE qty_on_hand <> 0;

CREATE OR REPLACE FUNCTION app.ledger_before_insert() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE
    v_lot       app.lots%ROWTYPE;
    v_loc       app.locations%ROWTYPE;
    v_on_hand   numeric(18,4);
BEGIN
    SELECT * INTO v_lot FROM app.lots WHERE id = NEW.lot_id;
    SELECT * INTO v_loc FROM app.locations WHERE id = NEW.location_id;

    IF v_lot.item_id <> NEW.item_id THEN
        RAISE EXCEPTION 'lot % belongs to item %, not %', v_lot.lot_number, v_lot.item_id, NEW.item_id;
    END IF;

    -- Snapshot the tax state and premises from the location.
    NEW.tax_state   := v_loc.tax_state;
    NEW.premises_id := v_loc.premises_id;

    -- Interlock 1: nothing leaves a lot that is not released, except a
    -- destruction/adjustment/reversal/transfer (moving a quarantined pallet is fine).
    IF NEW.qty_base < 0 AND v_lot.quality_status <> 'released'
       AND NEW.txn_type IN ('issue','packaging_output','removal') THEN
        RAISE EXCEPTION 'lot % is % and cannot be used or removed', v_lot.lot_number, v_lot.quality_status
            USING ERRCODE = 'check_violation';
    END IF;

    -- Interlock 2: no negative stock unless the location allows it.
    SELECT COALESCE(qty_on_hand, 0) INTO v_on_hand FROM app.inventory_balances
     WHERE item_id = NEW.item_id AND lot_id = NEW.lot_id AND location_id = NEW.location_id;
    IF COALESCE(v_on_hand, 0) + NEW.qty_base < 0 AND NOT v_loc.allow_negative THEN
        RAISE EXCEPTION 'insufficient stock of lot % at %: on hand %, requested %',
            v_lot.lot_number, v_loc.name, COALESCE(v_on_hand, 0), NEW.qty_base
            USING ERRCODE = 'check_violation';
    END IF;

    RETURN NEW;
END $$;

CREATE OR REPLACE FUNCTION app.ledger_after_insert() RETURNS trigger
LANGUAGE plpgsql AS $$
BEGIN
    INSERT INTO app.inventory_balances (item_id, lot_id, location_id, qty_on_hand, updated_at)
    VALUES (NEW.item_id, NEW.lot_id, NEW.location_id, NEW.qty_base, now())
    ON CONFLICT (item_id, lot_id, location_id)
    DO UPDATE SET qty_on_hand = app.inventory_balances.qty_on_hand + EXCLUDED.qty_on_hand, updated_at = now();
    RETURN NULL;
END $$;

CREATE TRIGGER inventory_transactions_before BEFORE INSERT ON app.inventory_transactions
    FOR EACH ROW EXECUTE FUNCTION app.ledger_before_insert();
CREATE TRIGGER inventory_transactions_after AFTER INSERT ON app.inventory_transactions
    FOR EACH ROW EXECUTE FUNCTION app.ledger_after_insert();

-- Interlock 3: a transfer between locations of different tax state must be a
-- removal or return, never a plain transfer. Checked on the group after insert.
CREATE OR REPLACE FUNCTION app.ledger_check_tax_state_group() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE n int;
BEGIN
    SELECT count(DISTINCT tax_state) INTO n
      FROM app.inventory_transactions WHERE group_id = NEW.group_id;
    IF n > 1 AND NEW.reference_kind NOT IN ('removal','return') THEN
        RAISE EXCEPTION 'a % cannot move stock between bonded and tax-paid locations; record a removal or return',
            NEW.reference_kind USING ERRCODE = 'check_violation';
    END IF;
    RETURN NULL;
END $$;
CREATE TRIGGER inventory_transactions_tax_state AFTER INSERT ON app.inventory_transactions
    FOR EACH ROW EXECUTE FUNCTION app.ledger_check_tax_state_group();

CREATE OR REPLACE FUNCTION app.rebuild_inventory_balances() RETURNS void
LANGUAGE sql AS $$
    UPDATE app.inventory_balances SET qty_on_hand = 0;
    INSERT INTO app.inventory_balances (item_id, lot_id, location_id, qty_on_hand, updated_at)
    SELECT item_id, lot_id, location_id, sum(qty_base), now()
      FROM app.inventory_transactions GROUP BY 1,2,3
    ON CONFLICT (item_id, lot_id, location_id)
    DO UPDATE SET qty_on_hand = EXCLUDED.qty_on_hand, updated_at = now();
$$;

-- Documents the screens work with --------------------------------------------------
CREATE TABLE app.inventory_transfers (
    id               bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    number           text NOT NULL UNIQUE,
    from_location_id bigint NOT NULL REFERENCES app.locations(id),
    to_location_id   bigint NOT NULL REFERENCES app.locations(id),
    status           text NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','posted','cancelled')),
    transferred_at   timestamptz NOT NULL DEFAULT now(),
    notes            text,
    created_by       bigint REFERENCES app.users(id),
    posted_by        bigint REFERENCES app.users(id),
    posted_at        timestamptz,
    created_at       timestamptz NOT NULL DEFAULT now(),
    updated_at       timestamptz NOT NULL DEFAULT now(),
    CHECK (from_location_id <> to_location_id)
);
CREATE TRIGGER inventory_transfers_touch BEFORE UPDATE ON app.inventory_transfers FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

CREATE TABLE app.inventory_transfer_lines (
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    transfer_id bigint NOT NULL REFERENCES app.inventory_transfers(id) ON DELETE CASCADE,
    item_id     bigint NOT NULL REFERENCES app.items(id),
    lot_id      bigint NOT NULL REFERENCES app.lots(id),
    qty_base    numeric(18,4) NOT NULL CHECK (qty_base > 0)
);

CREATE TABLE app.inventory_adjustments (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    number         text NOT NULL UNIQUE,
    location_id    bigint NOT NULL REFERENCES app.locations(id),
    reason_code_id bigint NOT NULL REFERENCES app.reason_codes(id),
    status         text NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','pending_approval','posted','cancelled')),
    adjusted_at    timestamptz NOT NULL DEFAULT now(),
    notes          text,
    created_by     bigint REFERENCES app.users(id),
    approved_by    bigint REFERENCES app.users(id),
    approved_at    timestamptz,
    posted_by      bigint REFERENCES app.users(id),
    posted_at      timestamptz,
    created_at     timestamptz NOT NULL DEFAULT now(),
    updated_at     timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER inventory_adjustments_touch BEFORE UPDATE ON app.inventory_adjustments FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

CREATE TABLE app.inventory_adjustment_lines (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    adjustment_id  bigint NOT NULL REFERENCES app.inventory_adjustments(id) ON DELETE CASCADE,
    item_id        bigint NOT NULL REFERENCES app.items(id),
    lot_id         bigint NOT NULL REFERENCES app.lots(id),
    qty_delta_base numeric(18,4) NOT NULL CHECK (qty_delta_base <> 0),
    unit_cost_base numeric(18,6),
    note           text
);

CREATE TABLE app.inventory_counts (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    number       text NOT NULL UNIQUE,
    location_id  bigint NOT NULL REFERENCES app.locations(id),
    kind         text NOT NULL DEFAULT 'cycle' CHECK (kind IN ('cycle','physical')),
    status       text NOT NULL DEFAULT 'open' CHECK (status IN ('open','counting','review','approved','cancelled')),
    started_at   timestamptz NOT NULL DEFAULT now(),
    started_by   bigint REFERENCES app.users(id),
    completed_at timestamptz,
    approved_by  bigint REFERENCES app.users(id),
    approved_at  timestamptz,
    notes        text,
    created_at   timestamptz NOT NULL DEFAULT now(),
    updated_at   timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER inventory_counts_touch BEFORE UPDATE ON app.inventory_counts FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

CREATE TABLE app.inventory_count_lines (
    id                bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    count_id          bigint NOT NULL REFERENCES app.inventory_counts(id) ON DELETE CASCADE,
    item_id           bigint NOT NULL REFERENCES app.items(id),
    lot_id            bigint NOT NULL REFERENCES app.lots(id),
    qty_expected_base numeric(18,4) NOT NULL,
    qty_counted_base  numeric(18,4),
    variance_base     numeric(18,4) GENERATED ALWAYS AS (qty_counted_base - qty_expected_base) STORED,
    counted_by        bigint REFERENCES app.users(id),
    counted_at        timestamptz,
    note              text,
    UNIQUE (count_id, item_id, lot_id)
);
