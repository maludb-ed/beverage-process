-- 011_removals_compliance.sql — customers as destinations, removals and
-- returns, TTB line mapping, period reports. Slice 10.
SET search_path = app, public;

CREATE TABLE app.customers (
    id                    bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name                  text NOT NULL,
    kind                  text NOT NULL CHECK (kind IN ('distributor','retailer','taproom','consumer','bonded_premises','other')),
    default_destination   text NOT NULL DEFAULT 'tax_paid_sale' CHECK (default_destination IN ('tax_paid_sale','taproom_transfer','in_bond_transfer','export')),
    permit_number         text,                              -- for in-bond transfers
    contact_name          text,
    email                 text,
    phone                 text,
    address               text,
    notes                 text,
    active                boolean NOT NULL DEFAULT true,
    created_at            timestamptz NOT NULL DEFAULT now(),
    updated_at            timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER customers_touch BEFORE UPDATE ON app.customers FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

ALTER TABLE app.keg_movements ADD CONSTRAINT keg_movements_customer_fk FOREIGN KEY (customer_id) REFERENCES app.customers(id);

CREATE TABLE app.removals (
    id                bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    number            text NOT NULL UNIQUE,
    premises_id       bigint NOT NULL REFERENCES app.premises(id),
    direction         text NOT NULL DEFAULT 'out' CHECK (direction IN ('out','in')),   -- in = return
    destination_kind  text NOT NULL CHECK (destination_kind IN (
                          'tax_paid_sale','taproom_transfer','in_bond_transfer','export','sample_testing',
                          'destroyed','breakage','family_use','return_from_customer')),
    customer_id       bigint REFERENCES app.customers(id),
    from_location_id  bigint REFERENCES app.locations(id),
    to_location_id    bigint REFERENCES app.locations(id),     -- taproom transfer / return destination
    removed_at        timestamptz NOT NULL DEFAULT now(),
    status            text NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','posted','reversed','cancelled')),
    reference         text,                                     -- invoice or BOL number from outside
    tax_determined    boolean NOT NULL DEFAULT false,
    wine_gallons      numeric(14,4),
    tax_class         text,
    tax_rate_per_gal  numeric(10,4),
    cbma_credit_per_gal numeric(10,4),
    tax_amount        numeric(14,2),
    notes             text,
    created_by        bigint REFERENCES app.users(id),
    posted_by         bigint REFERENCES app.users(id),
    posted_at         timestamptz,
    reversed_by_id    bigint REFERENCES app.removals(id),
    created_at        timestamptz NOT NULL DEFAULT now(),
    updated_at        timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX removals_period_idx ON app.removals (premises_id, removed_at, destination_kind);
CREATE TRIGGER removals_touch BEFORE UPDATE ON app.removals FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

ALTER TABLE app.keg_movements ADD CONSTRAINT keg_movements_removal_fk FOREIGN KEY (removal_id) REFERENCES app.removals(id);

CREATE TABLE app.removal_lines (
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    removal_id  bigint NOT NULL REFERENCES app.removals(id) ON DELETE CASCADE,
    lot_id      bigint NOT NULL REFERENCES app.lots(id),       -- a finished lot
    units       int NOT NULL CHECK (units > 0),
    volume_l    numeric(14,3) NOT NULL,
    keg_id      bigint REFERENCES app.kegs(id),
    tax_class   text,
    note        text
);
CREATE INDEX removal_lines_lot_idx ON app.removal_lines (lot_id);

-- Which report line a ledger event feeds. Derivation, not data entry.
CREATE TABLE app.ttb_line_map (
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    form_code   text NOT NULL,
    section     text NOT NULL,                                  -- 'A' bulk, 'B' bottled, 'IV' materials
    line_code   text NOT NULL,
    label       text NOT NULL,
    source      text NOT NULL CHECK (source IN ('ledger','loss','removal','balance','materials')),
    match       jsonb NOT NULL,                                 -- e.g. {"ttb_category":"produced"} or {"destination_kind":"tax_paid_sale","bulk":false}
    sign        int NOT NULL DEFAULT 1,
    display_order int NOT NULL,
    UNIQUE (form_code, section, line_code)
);
-- Form 5120.17 (Report of Wine Premises Operations), the lines the cider slices produce.
INSERT INTO app.ttb_line_map (form_code, section, line_code, label, source, match, sign, display_order) VALUES
    ('5120.17','A','1',  'On hand beginning of period (bulk)',          'balance',   '{"bulk":true,"when":"start"}', 1, 10),
    ('5120.17','A','2',  'Produced by fermentation',                    'ledger',    '{"ttb_category":"produced","bulk":true}', 1, 20),
    ('5120.17','A','3',  'Sweetening',                                  'ledger',    '{"ttb_category":"used_in_production","purpose":"sweetener"}', 1, 30),
    ('5120.17','A','5',  'Blending (in)',                               'ledger',    '{"ttb_category":"blended_in"}', 1, 50),
    ('5120.17','A','7',  'Received in bond',                            'ledger',    '{"ttb_category":"received","bulk":true,"in_bond":true}', 1, 70),
    ('5120.17','A','9',  'Inventory gains',                             'ledger',    '{"ttb_category":"inventory_gain","bulk":true}', 1, 90),
    ('5120.17','A','13', 'Bottled or packed',                           'ledger',    '{"ttb_category":"bottled"}', 1, 130),
    ('5120.17','A','14', 'Removed taxpaid (bulk)',                      'removal',   '{"destination_kind":"tax_paid_sale","bulk":true}', 1, 140),
    ('5120.17','A','15', 'Transferred in bond (bulk)',                  'removal',   '{"destination_kind":"in_bond_transfer","bulk":true}', 1, 150),
    ('5120.17','A','23', 'Used for testing',                            'loss',      '{"ttb_category":"testing","bulk":true}', 1, 230),
    ('5120.17','A','29', 'Losses other than inventory',                 'loss',      '{"ttb_category":"casualty_loss","bulk":true}', 1, 290),
    ('5120.17','A','30', 'Inventory losses',                            'loss',      '{"ttb_category":"inventory_loss","bulk":true}', 1, 300),
    ('5120.17','A','31', 'On hand end of period (bulk)',                'balance',   '{"bulk":true,"when":"end"}', 1, 310),
    ('5120.17','B','1',  'On hand beginning of period (bottled)',       'balance',   '{"bulk":false,"when":"start"}', 1, 10),
    ('5120.17','B','2',  'Bottled or packed',                           'ledger',    '{"ttb_category":"bottled"}', 1, 20),
    ('5120.17','B','3',  'Received in bond (bottled)',                  'ledger',    '{"ttb_category":"received","bulk":false,"in_bond":true}', 1, 30),
    ('5120.17','B','4',  'Taxpaid wine returned to bond',               'removal',   '{"destination_kind":"return_from_customer"}', 1, 40),
    ('5120.17','B','8',  'Removed taxpaid',                             'removal',   '{"destination_kind":"tax_paid_sale","bulk":false}', 1, 80),
    ('5120.17','B','8t', 'Removed taxpaid to taproom',                  'removal',   '{"destination_kind":"taproom_transfer"}', 1, 81),
    ('5120.17','B','9',  'Transferred in bond (bottled)',               'removal',   '{"destination_kind":"in_bond_transfer","bulk":false}', 1, 90),
    ('5120.17','B','11', 'Exported',                                    'removal',   '{"destination_kind":"export"}', 1, 110),
    ('5120.17','B','12', 'Family use',                                  'removal',   '{"destination_kind":"family_use"}', 1, 120),
    ('5120.17','B','13', 'Used for testing (bottled)',                  'removal',   '{"destination_kind":"sample_testing"}', 1, 130),
    ('5120.17','B','15', 'Destroyed',                                   'removal',   '{"destination_kind":"destroyed"}', 1, 150),
    ('5120.17','B','16', 'Breakage',                                    'removal',   '{"destination_kind":"breakage"}', 1, 160),
    ('5120.17','B','17', 'Inventory shortage',                          'loss',      '{"ttb_category":"shortage","bulk":false}', 1, 170),
    ('5120.17','B','18', 'On hand end of period (bottled)',             'balance',   '{"bulk":false,"when":"end"}', 1, 180),
    ('5120.17','IV','1', 'Fruit received (tons)',                       'materials', '{"ttb_material_category":"fruit"}', 1, 10),
    ('5120.17','IV','2', 'Juice received (gallons)',                    'materials', '{"ttb_material_category":"juice"}', 1, 20),
    ('5120.17','IV','3', 'Sugar received (pounds)',                     'materials', '{"ttb_material_category":"sugar"}', 1, 30);

CREATE TABLE app.period_reports (
    id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    number        text NOT NULL UNIQUE,
    premises_id   bigint NOT NULL REFERENCES app.premises(id),
    form_code     text NOT NULL,
    period_start  date NOT NULL,
    period_end    date NOT NULL,
    status        text NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','final','filed','amended')),
    generated_at  timestamptz NOT NULL DEFAULT now(),
    generated_by  bigint REFERENCES app.users(id),
    finalized_at  timestamptz,
    filed_at      timestamptz,
    filed_by      bigint REFERENCES app.users(id),
    totals        jsonb NOT NULL DEFAULT '{}'::jsonb,         -- {"tax_class":{"hard_cider":{"gallons":..,"tax":..}}}
    notes         text,
    created_at    timestamptz NOT NULL DEFAULT now(),
    updated_at    timestamptz NOT NULL DEFAULT now(),
    UNIQUE (premises_id, form_code, period_start, period_end),
    CHECK (period_end >= period_start)
);
CREATE TRIGGER period_reports_touch BEFORE UPDATE ON app.period_reports FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

CREATE TABLE app.period_report_lines (
    id              bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    report_id       bigint NOT NULL REFERENCES app.period_reports(id) ON DELETE CASCADE,
    section         text NOT NULL,
    line_code       text NOT NULL,
    label           text NOT NULL,
    tax_class       text,                                        -- 5120.17 is by tax class column
    value           numeric(16,4) NOT NULL DEFAULT 0,
    unit            text NOT NULL DEFAULT 'gal',
    source_ids      jsonb NOT NULL DEFAULT '[]'::jsonb,          -- the ledger / loss / removal ids behind the number
    is_adjustment   boolean NOT NULL DEFAULT false,              -- prior-period adjustment
    UNIQUE (report_id, section, line_code, tax_class)
);
