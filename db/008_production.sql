-- 008_production.sql — production orders, allocations, vessels in use, press
-- runs, batches and the batch graph, consumptions, transfers, splits, blends,
-- losses, readings, yeast harvests, co-product disposition. Slices 5 and 6.
SET search_path = app, public;

CREATE TABLE app.production_orders (
    id                  bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    number              text NOT NULL UNIQUE,
    premises_id         bigint NOT NULL REFERENCES app.premises(id),
    product_id          bigint NOT NULL REFERENCES app.products(id),
    recipe_version_id   bigint NOT NULL REFERENCES app.recipe_versions(id),
    planned_volume_l    numeric(14,3) NOT NULL CHECK (planned_volume_l > 0),
    planned_pitch_on    date,
    planned_package_on  date,
    status              text NOT NULL DEFAULT 'planned' CHECK (status IN ('planned','released','in_progress','complete','closed','cancelled')),
    notes               text,
    created_by          bigint REFERENCES app.users(id),
    released_by         bigint REFERENCES app.users(id),
    released_at         timestamptz,
    closed_by           bigint REFERENCES app.users(id),
    closed_at           timestamptz,
    created_at          timestamptz NOT NULL DEFAULT now(),
    updated_at          timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX production_orders_status_idx ON app.production_orders (status, planned_pitch_on);
CREATE TRIGGER production_orders_touch BEFORE UPDATE ON app.production_orders FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

-- Planned vessel use; overlaps warn in the UI, never block.
CREATE TABLE app.production_order_vessels (
    id                  bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    production_order_id bigint NOT NULL REFERENCES app.production_orders(id) ON DELETE CASCADE,
    vessel_id           bigint NOT NULL REFERENCES app.vessels(id),
    role                text NOT NULL DEFAULT 'primary' CHECK (role IN ('primary','maturation','brite','blend')),
    planned_from        date NOT NULL,
    planned_to          date NOT NULL,
    CHECK (planned_to >= planned_from)
);
CREATE INDEX production_order_vessels_vessel_idx ON app.production_order_vessels (vessel_id, planned_from, planned_to);

CREATE TABLE app.allocations (
    id                  bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    production_order_id bigint NOT NULL REFERENCES app.production_orders(id) ON DELETE CASCADE,
    item_id             bigint NOT NULL REFERENCES app.items(id),
    lot_id              bigint REFERENCES app.lots(id),       -- NULL = soft (item level)
    qty_base            numeric(18,4) NOT NULL CHECK (qty_base > 0),
    created_at          timestamptz NOT NULL DEFAULT now(),
    released_at         timestamptz                           -- consumed or cancelled
);
CREATE INDEX allocations_open_idx ON app.allocations (item_id, lot_id) WHERE released_at IS NULL;

-- Batches --------------------------------------------------------------------------------
CREATE TABLE app.batches (
    id                       bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    number                   text NOT NULL UNIQUE,
    premises_id              bigint NOT NULL REFERENCES app.premises(id),
    product_id               bigint NOT NULL REFERENCES app.products(id),
    recipe_version_id        bigint REFERENCES app.recipe_versions(id),
    production_order_id      bigint REFERENCES app.production_orders(id),
    origin_kind              text NOT NULL DEFAULT 'pitch' CHECK (origin_kind IN ('pitch','split','blend')),
    started_at               timestamptz NOT NULL DEFAULT now(),
    current_stage_code       text NOT NULL DEFAULT 'pitch' REFERENCES app.stages(code),
    status                   text NOT NULL DEFAULT 'active' CHECK (status IN ('active','packaged','dumped','closed')),
    current_volume_l         numeric(14,3) NOT NULL DEFAULT 0,  -- maintained by execution handlers
    fruit_share_pct          numeric(5,2),                      -- derived through blends
    tax_class_derived        text,
    tax_class_override       text,
    tax_class_override_reason_code_id bigint REFERENCES app.reason_codes(id),
    tax_class_override_by    bigint REFERENCES app.users(id),
    tax_class_override_at    timestamptz,
    notes                    text,
    closed_at                timestamptz,
    created_by               bigint REFERENCES app.users(id),
    created_at               timestamptz NOT NULL DEFAULT now(),
    updated_at               timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX batches_status_idx ON app.batches (status, current_stage_code);
CREATE TRIGGER batches_touch BEFORE UPDATE ON app.batches FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

-- Explicit genealogy links, written by split and blend handlers.
CREATE TABLE app.batch_lineage (
    id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    child_batch_id  bigint NOT NULL REFERENCES app.batches(id),
    parent_batch_id bigint NOT NULL REFERENCES app.batches(id),
    event_kind      text NOT NULL CHECK (event_kind IN ('split','blend')),
    event_id        bigint NOT NULL,
    volume_l        numeric(14,3) NOT NULL CHECK (volume_l > 0),
    fraction        numeric(8,6) NOT NULL CHECK (fraction > 0 AND fraction <= 1),
    created_at      timestamptz NOT NULL DEFAULT now(),
    CHECK (child_batch_id <> parent_batch_id)
);
CREATE INDEX batch_lineage_child_idx  ON app.batch_lineage (child_batch_id);
CREATE INDEX batch_lineage_parent_idx ON app.batch_lineage (parent_batch_id);

-- Press runs: fruit lots in, juice lots and pomace out ----------------------------------
CREATE TABLE app.press_runs (
    id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    number          text NOT NULL UNIQUE,
    premises_id     bigint NOT NULL REFERENCES app.premises(id),
    press_vessel_id bigint REFERENCES app.vessels(id),
    run_on          date NOT NULL DEFAULT current_date,
    started_at      timestamptz,
    finished_at     timestamptz,
    status          text NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','posted','cancelled')),
    fruit_kg_total  numeric(14,3),
    juice_l_total   numeric(14,3),
    pomace_kg_total numeric(14,3),
    yield_l_per_kg  numeric(10,6),
    notes           text,
    created_by      bigint REFERENCES app.users(id),
    posted_by       bigint REFERENCES app.users(id),
    posted_at       timestamptz,
    created_at      timestamptz NOT NULL DEFAULT now(),
    updated_at      timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER press_runs_touch BEFORE UPDATE ON app.press_runs FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

CREATE TABLE app.press_run_inputs (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    press_run_id bigint NOT NULL REFERENCES app.press_runs(id) ON DELETE CASCADE,
    lot_id       bigint NOT NULL REFERENCES app.lots(id),     -- fruit lot
    qty_kg       numeric(14,3) NOT NULL CHECK (qty_kg > 0)
);

CREATE TABLE app.press_run_outputs (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    press_run_id bigint NOT NULL REFERENCES app.press_runs(id) ON DELETE CASCADE,
    kind         text NOT NULL CHECK (kind IN ('juice','pomace')),
    item_id      bigint NOT NULL REFERENCES app.items(id),
    lot_id       bigint REFERENCES app.lots(id),              -- created at post
    qty_base     numeric(14,3) NOT NULL CHECK (qty_base > 0), -- L for juice, kg for pomace
    brix         numeric(6,2),
    vessel_id    bigint REFERENCES app.vessels(id),           -- juice goes into a vessel
    location_id  bigint REFERENCES app.locations(id)          -- pomace goes to a location
);

-- Vessel occupancy: one occupant per vessel at a time -------------------------------
CREATE TABLE app.vessel_occupancies (
    id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    vessel_id     bigint NOT NULL REFERENCES app.vessels(id),
    occupant_kind text NOT NULL CHECK (occupant_kind IN ('lot','batch')),
    occupant_id   bigint NOT NULL,
    volume_l      numeric(14,3) NOT NULL CHECK (volume_l >= 0),
    from_at       timestamptz NOT NULL DEFAULT now(),
    to_at         timestamptz,
    CHECK (to_at IS NULL OR to_at >= from_at)
);
CREATE UNIQUE INDEX vessel_occupancies_one_open ON app.vessel_occupancies (vessel_id) WHERE to_at IS NULL;
CREATE INDEX vessel_occupancies_occupant_idx ON app.vessel_occupancies (occupant_kind, occupant_id) WHERE to_at IS NULL;

-- Stage events -----------------------------------------------------------------------
CREATE TABLE app.stage_events (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    batch_id     bigint NOT NULL REFERENCES app.batches(id),
    stage_code   text NOT NULL REFERENCES app.stages(code),
    entered_at   timestamptz NOT NULL DEFAULT now(),
    left_at      timestamptz,
    volume_in_l  numeric(14,3),
    volume_out_l numeric(14,3),
    actor_id     bigint REFERENCES app.users(id),
    note         text
);
CREATE INDEX stage_events_batch_idx ON app.stage_events (batch_id, entered_at);

-- Consumptions: an item lot used by a batch or a press run ----------------------------
CREATE TABLE app.consumptions (
    id               bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    batch_id         bigint REFERENCES app.batches(id),
    press_run_id     bigint REFERENCES app.press_runs(id),
    item_id          bigint NOT NULL REFERENCES app.items(id),
    lot_id           bigint NOT NULL REFERENCES app.lots(id),
    qty_base         numeric(18,4) NOT NULL CHECK (qty_base > 0),
    purpose          text NOT NULL CHECK (purpose IN ('base_juice','yeast','nutrient','sulfite','enzyme','sweetener','acid','fining','fruit','other')),
    stage_code       text REFERENCES app.stages(code),
    planned_qty_base numeric(18,4),
    consumed_at      timestamptz NOT NULL DEFAULT now(),
    actor_id         bigint REFERENCES app.users(id),
    ledger_group_id  uuid,
    note             text,
    CHECK ((batch_id IS NOT NULL) <> (press_run_id IS NOT NULL))
);
CREATE INDEX consumptions_batch_idx ON app.consumptions (batch_id);
CREATE INDEX consumptions_lot_idx   ON app.consumptions (lot_id);

CREATE TABLE app.batch_transfers (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    batch_id       bigint NOT NULL REFERENCES app.batches(id),
    from_vessel_id bigint NOT NULL REFERENCES app.vessels(id),
    to_vessel_id   bigint NOT NULL REFERENCES app.vessels(id),
    volume_l       numeric(14,3) NOT NULL CHECK (volume_l > 0),
    loss_l         numeric(14,3) NOT NULL DEFAULT 0 CHECK (loss_l >= 0),
    transferred_at timestamptz NOT NULL DEFAULT now(),
    actor_id       bigint REFERENCES app.users(id),
    note           text,
    CHECK (from_vessel_id <> to_vessel_id)
);

CREATE TABLE app.batch_splits (
    id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    source_batch_id bigint NOT NULL REFERENCES app.batches(id),
    split_at        timestamptz NOT NULL DEFAULT now(),
    actor_id        bigint REFERENCES app.users(id),
    note            text
);
CREATE TABLE app.batch_split_outputs (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    split_id       bigint NOT NULL REFERENCES app.batch_splits(id) ON DELETE CASCADE,
    child_batch_id bigint NOT NULL REFERENCES app.batches(id),
    vessel_id      bigint NOT NULL REFERENCES app.vessels(id),
    volume_l       numeric(14,3) NOT NULL CHECK (volume_l > 0)
);

CREATE TABLE app.batch_blends (
    id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    result_batch_id bigint NOT NULL REFERENCES app.batches(id),
    vessel_id       bigint NOT NULL REFERENCES app.vessels(id),
    volume_out_l    numeric(14,3) NOT NULL CHECK (volume_out_l > 0),
    blended_at      timestamptz NOT NULL DEFAULT now(),
    actor_id        bigint REFERENCES app.users(id),
    note            text
);
CREATE TABLE app.batch_blend_inputs (
    id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    blend_id        bigint NOT NULL REFERENCES app.batch_blends(id) ON DELETE CASCADE,
    source_batch_id bigint NOT NULL REFERENCES app.batches(id),
    volume_l        numeric(14,3) NOT NULL CHECK (volume_l > 0)
);

-- Losses: expected (in the recipe) or exceptional (needs a reason, maybe approval)
CREATE TABLE app.loss_events (
    id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    target_kind     text NOT NULL CHECK (target_kind IN ('batch','lot')),
    target_id       bigint NOT NULL,
    premises_id     bigint NOT NULL REFERENCES app.premises(id),
    stage_code      text REFERENCES app.stages(code),
    qty_base        numeric(18,4) NOT NULL CHECK (qty_base > 0),
    unit_code       text NOT NULL DEFAULT 'L',
    reason_code_id  bigint NOT NULL REFERENCES app.reason_codes(id),
    ttb_category    text NOT NULL,                          -- copied from the reason code at posting
    reportable      boolean NOT NULL DEFAULT true,
    classification  text NOT NULL CHECK (classification IN ('expected','exceptional')),
    approved_by     bigint REFERENCES app.users(id),
    approved_at     timestamptz,
    occurred_at     timestamptz NOT NULL DEFAULT now(),
    actor_id        bigint REFERENCES app.users(id),
    ledger_group_id uuid,
    note            text
);
CREATE INDEX loss_events_target_idx ON app.loss_events (target_kind, target_id);
CREATE INDEX loss_events_period_idx ON app.loss_events (premises_id, occurred_at);

-- Readings: one entity for every measurement ---------------------------------------
CREATE TABLE app.readings (
    id                    bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    target_kind           text NOT NULL CHECK (target_kind IN ('batch','lot','vessel')),
    target_id             bigint NOT NULL,
    measurement_type_code text NOT NULL REFERENCES app.measurement_types(code),
    value                 numeric(12,4) NOT NULL,
    taken_at              timestamptz NOT NULL DEFAULT now(),
    stage_code            text REFERENCES app.stages(code),
    method                text,
    is_lab                boolean NOT NULL DEFAULT false,
    analyst_id            bigint REFERENCES app.users(id),
    spec_id               bigint REFERENCES app.specs(id),
    spec_result           text NOT NULL DEFAULT 'none' CHECK (spec_result IN ('none','pass','fail')),
    attachment_id         bigint REFERENCES app.attachments(id),
    note                  text,
    created_at            timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX readings_target_idx ON app.readings (target_kind, target_id, measurement_type_code, taken_at);

-- Yeast harvests: a yeast lot with a generation and a source batch ---------------------
CREATE TABLE app.yeast_harvests (
    id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    source_batch_id bigint NOT NULL REFERENCES app.batches(id),
    lot_id        bigint NOT NULL UNIQUE REFERENCES app.lots(id),
    generation    int NOT NULL CHECK (generation >= 1),
    harvested_at  timestamptz NOT NULL DEFAULT now(),
    volume_l      numeric(10,3),
    cell_count    numeric(10,2),
    viability_pct numeric(5,2),
    actor_id      bigint REFERENCES app.users(id),
    note          text
);

-- Pomace and other co-products leaving ----------------------------------------------
CREATE TABLE app.co_product_dispositions (
    id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    lot_id          bigint NOT NULL REFERENCES app.lots(id),
    qty_base        numeric(18,4) NOT NULL CHECK (qty_base > 0),
    destination     text NOT NULL CHECK (destination IN ('compost','farm','sale','waste','other')),
    recipient       text,
    disposed_at     timestamptz NOT NULL DEFAULT now(),
    actor_id        bigint REFERENCES app.users(id),
    ledger_group_id uuid,
    note            text
);
