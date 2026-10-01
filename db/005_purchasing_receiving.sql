-- 005_purchasing_receiving.sql — purchase orders, receipts, weigh tags, lots,
-- lot attributes, certificates, release decisions. Slice 2 (the exemplar).
SET search_path = app, public;

CREATE TABLE app.purchase_orders (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    number       text NOT NULL UNIQUE,
    supplier_id  bigint NOT NULL REFERENCES app.suppliers(id),
    premises_id  bigint NOT NULL REFERENCES app.premises(id),
    status       text NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','open','partial','closed','closed_short','cancelled')),
    ordered_on   date,
    expected_on  date,
    notes        text,
    approved_by  bigint REFERENCES app.users(id),
    approved_at  timestamptz,
    created_by   bigint REFERENCES app.users(id),
    created_at   timestamptz NOT NULL DEFAULT now(),
    updated_at   timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX purchase_orders_status_idx ON app.purchase_orders (status, expected_on);
CREATE TRIGGER purchase_orders_touch BEFORE UPDATE ON app.purchase_orders FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

CREATE TABLE app.purchase_order_lines (
    id                   bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    purchase_order_id    bigint NOT NULL REFERENCES app.purchase_orders(id) ON DELETE CASCADE,
    line_no              int NOT NULL,
    item_id              bigint NOT NULL REFERENCES app.items(id),
    qty_ordered          numeric(18,4) NOT NULL CHECK (qty_ordered > 0),   -- in purchase unit
    purchase_unit_code   text NOT NULL,
    to_base_factor       numeric(18,8) NOT NULL,
    qty_ordered_base     numeric(18,4) GENERATED ALWAYS AS (qty_ordered * to_base_factor) STORED,
    unit_price           numeric(18,4) NOT NULL DEFAULT 0,                 -- per purchase unit
    expected_on          date,
    qty_received_base    numeric(18,4) NOT NULL DEFAULT 0,
    status               text NOT NULL DEFAULT 'open' CHECK (status IN ('open','partial','received','closed_short','cancelled')),
    close_reason_code_id bigint REFERENCES app.reason_codes(id),
    notes                text,
    UNIQUE (purchase_order_id, line_no)
);

CREATE TABLE app.goods_receipts (
    id                bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    number            text NOT NULL UNIQUE,
    premises_id       bigint NOT NULL REFERENCES app.premises(id),
    supplier_id       bigint NOT NULL REFERENCES app.suppliers(id),
    purchase_order_id bigint REFERENCES app.purchase_orders(id),           -- NULL = unplanned receipt
    received_at       timestamptz NOT NULL DEFAULT now(),
    receiving_location_id bigint NOT NULL REFERENCES app.locations(id),
    status            text NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','posted','cancelled')),
    delivery_note_ref text,
    notes             text,
    received_by       bigint REFERENCES app.users(id),
    posted_by         bigint REFERENCES app.users(id),
    posted_at         timestamptz,
    created_at        timestamptz NOT NULL DEFAULT now(),
    updated_at        timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX goods_receipts_received_idx ON app.goods_receipts (received_at DESC);
CREATE TRIGGER goods_receipts_touch BEFORE UPDATE ON app.goods_receipts FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

-- Lots must exist before receipt lines reference them, but a lot's source is
-- the receipt line; the FK from lots to receipt lines is deferred by design
-- (source_kind/source_id, not a hard FK).
CREATE TABLE app.lots (
    id                  bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    lot_number          text NOT NULL UNIQUE,
    item_id             bigint NOT NULL REFERENCES app.items(id),
    premises_id         bigint NOT NULL REFERENCES app.premises(id),
    supplier_lot_number text,
    supplier_id         bigint REFERENCES app.suppliers(id),
    received_on         date,
    produced_on         date,
    expires_on          date,
    quality_status      text NOT NULL DEFAULT 'released' CHECK (quality_status IN ('quarantine','hold','released','rejected')),
    unit_cost_base      numeric(18,6) NOT NULL DEFAULT 0,                  -- per base unit
    source_kind         text NOT NULL CHECK (source_kind IN ('receipt_line','press_run','batch','packaging_run','yeast_harvest','adjustment','opening')),
    source_id           bigint,
    notes               text,
    created_by          bigint REFERENCES app.users(id),
    created_at          timestamptz NOT NULL DEFAULT now(),
    updated_at          timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX lots_item_idx    ON app.lots (item_id, quality_status);
CREATE INDEX lots_expires_idx ON app.lots (expires_on) WHERE expires_on IS NOT NULL;
CREATE INDEX lots_source_idx  ON app.lots (source_kind, source_id);
CREATE TRIGGER lots_touch BEFORE UPDATE ON app.lots FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

-- Typed attributes: variety, orchard, block, brix, ph, ta, free_so2, total_so2,
-- abv, co2_g_100ml, fruit_share_pct, strain, generation, viability_pct, alpha_acid_pct ...
CREATE TABLE app.lot_attributes (
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    lot_id      bigint NOT NULL REFERENCES app.lots(id) ON DELETE CASCADE,
    key         text NOT NULL,
    value_num   numeric(18,6),
    value_text  text,
    unit_code   text,
    source      text NOT NULL DEFAULT 'manual' CHECK (source IN ('manual','coa','weigh_tag','reading','press_run','packaging_run','derived')),
    recorded_at timestamptz NOT NULL DEFAULT now(),
    recorded_by bigint REFERENCES app.users(id),
    UNIQUE (lot_id, key),
    CHECK (value_num IS NOT NULL OR value_text IS NOT NULL)
);

CREATE TABLE app.goods_receipt_lines (
    id                   bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    goods_receipt_id     bigint NOT NULL REFERENCES app.goods_receipts(id) ON DELETE CASCADE,
    line_no              int NOT NULL,
    purchase_order_line_id bigint REFERENCES app.purchase_order_lines(id),
    item_id              bigint NOT NULL REFERENCES app.items(id),
    qty_received         numeric(18,4) NOT NULL CHECK (qty_received >= 0),  -- in purchase unit
    purchase_unit_code   text NOT NULL,
    to_base_factor       numeric(18,8) NOT NULL,
    qty_base             numeric(18,4) NOT NULL,                             -- catch-weight: from the weigh tag
    unit_cost_base       numeric(18,6) NOT NULL DEFAULT 0,
    discrepancy_kind     text NOT NULL DEFAULT 'none' CHECK (discrepancy_kind IN ('none','short','over','damaged','substituted')),
    discrepancy_note     text,
    supplier_lot_number  text,
    expires_on           date,
    lot_id               bigint REFERENCES app.lots(id),                     -- created at post
    putaway_location_id  bigint REFERENCES app.locations(id),
    notes                text,
    UNIQUE (goods_receipt_id, line_no)
);
CREATE INDEX goods_receipt_lines_po_line_idx ON app.goods_receipt_lines (purchase_order_line_id);

-- Fruit intake: a receipt line's weigh tag. Weights stored in kg, entered in lb.
CREATE TABLE app.weigh_tags (
    id                    bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    goods_receipt_line_id bigint NOT NULL UNIQUE REFERENCES app.goods_receipt_lines(id) ON DELETE CASCADE,
    tag_number            text,
    gross_kg              numeric(14,3) NOT NULL CHECK (gross_kg >= 0),
    tare_kg               numeric(14,3) NOT NULL DEFAULT 0 CHECK (tare_kg >= 0),
    net_kg                numeric(14,3) GENERATED ALWAYS AS (gross_kg - tare_kg) STORED,
    bin_count             int,
    variety               text,
    orchard               text,
    block                 text,
    brix_at_receipt       numeric(6,2),
    condition_note        text,
    weighed_at            timestamptz NOT NULL DEFAULT now(),
    weighed_by            bigint REFERENCES app.users(id)
);

-- Certificate values parsed from the CoA document (the file is an attachment).
CREATE TABLE app.certificates_of_analysis (
    id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    lot_id        bigint NOT NULL REFERENCES app.lots(id) ON DELETE CASCADE,
    attachment_id bigint REFERENCES app.attachments(id),
    issued_on     date,
    issuer        text,
    values_json   jsonb NOT NULL DEFAULT '{}'::jsonb,        -- raw parsed values; key ones copied to lot_attributes
    recorded_by   bigint REFERENCES app.users(id),
    created_at    timestamptz NOT NULL DEFAULT now()
);

-- Quality status changes on lots (slice 2) and batches (slice 8).
CREATE TABLE app.release_decisions (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    target_kind  text NOT NULL CHECK (target_kind IN ('lot','batch')),
    target_id    bigint NOT NULL,
    from_status  text NOT NULL,
    to_status    text NOT NULL,
    basis        text NOT NULL CHECK (basis IN ('coa','inspection','readings','sensory','override','other')),
    is_override  boolean NOT NULL DEFAULT false,
    reason_code_id bigint REFERENCES app.reason_codes(id),
    note         text,
    decided_by   bigint NOT NULL REFERENCES app.users(id),
    decided_at   timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX release_decisions_target_idx ON app.release_decisions (target_kind, target_id, decided_at DESC);
