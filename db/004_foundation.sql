-- 004_foundation.sql — the client, premises, units, items, suppliers,
-- locations, vessels, reason codes, attachments. Slice 1.
SET search_path = app, public;

-- One row per client database ------------------------------------------------
CREATE TABLE app.client_settings (
    id                  int PRIMARY KEY DEFAULT 1 CHECK (id = 1),
    client_name         text NOT NULL,
    subdomain           text NOT NULL,
    timezone            text NOT NULL DEFAULT 'America/New_York',
    volume_display_unit text NOT NULL DEFAULT 'gal',
    mass_display_unit   text NOT NULL DEFAULT 'lb',
    fruit_display_unit  text NOT NULL DEFAULT 'lb',
    settings            jsonb NOT NULL DEFAULT '{}'::jsonb,
    created_at          timestamptz NOT NULL DEFAULT now(),
    updated_at          timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER client_settings_touch BEFORE UPDATE ON app.client_settings FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

-- Premises: one TTB permit --------------------------------------------------------
CREATE TABLE app.premises (
    id                      bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name                    text NOT NULL,
    kind                    text NOT NULL CHECK (kind IN ('bonded_winery','brewery')),
    registry_number         text,                            -- e.g. BWN-XX-12345 or BR-XX-12345
    report_form             text NOT NULL CHECK (report_form IN ('5120.17','5130.9','5130.26')),
    filing_frequency        text NOT NULL DEFAULT 'monthly' CHECK (filing_frequency IN ('monthly','quarterly','annual')),
    tax_determination_point text NOT NULL DEFAULT 'removal' CHECK (tax_determination_point IN ('removal','packaging','designated_tank')),
    cbma_tier               text NOT NULL DEFAULT 'tier1' CHECK (cbma_tier IN ('none','tier1','tier2','tier3')),
    active                  boolean NOT NULL DEFAULT true,
    created_at              timestamptz NOT NULL DEFAULT now(),
    updated_at              timestamptz NOT NULL DEFAULT now(),
    CONSTRAINT premises_form_matches_kind CHECK (
        (kind = 'bonded_winery' AND report_form = '5120.17') OR
        (kind = 'brewery' AND report_form IN ('5130.9','5130.26')))
);
CREATE TRIGGER premises_touch BEFORE UPDATE ON app.premises FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

-- Units: metric base, domain display ------------------------------------------------
CREATE TABLE app.units (
    code           text PRIMARY KEY,
    name           text NOT NULL,
    dimension      text NOT NULL CHECK (dimension IN ('volume','mass','count')),
    to_base_factor numeric(18,8) NOT NULL,                   -- multiply to get base (L, kg, ea)
    is_base        boolean NOT NULL DEFAULT false,
    display_order  int NOT NULL DEFAULT 100
);
INSERT INTO app.units (code, name, dimension, to_base_factor, is_base, display_order) VALUES
    ('L',      'liter',            'volume', 1,              true,  10),
    ('mL',     'milliliter',       'volume', 0.001,          false, 11),
    ('hL',     'hectoliter',       'volume', 100,            false, 12),
    ('gal',    'US gallon',        'volume', 3.785411784,    false, 20),
    ('bbl',    'US beer barrel',   'volume', 117.347765304,  false, 21),
    ('floz',   'US fluid ounce',   'volume', 0.0295735296,   false, 22),
    ('kg',     'kilogram',         'mass',   1,              true,  30),
    ('g',      'gram',             'mass',   0.001,          false, 31),
    ('lb',     'pound',            'mass',   0.45359237,     false, 40),
    ('oz',     'ounce',            'mass',   0.0283495231,   false, 41),
    ('ton',    'US short ton',     'mass',   907.18474,      false, 42),
    ('bushel', 'bushel of apples', 'mass',   19.05087954,    false, 43),  -- 42 lb default; override per item
    ('ea',     'each',             'count',  1,              true,  50),
    ('case',   'case',             'count',  1,              false, 51);  -- items define units per case

-- Items ------------------------------------------------------------------------------------
CREATE TABLE app.items (
    id                     bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    code                   text NOT NULL,
    name                   text NOT NULL,
    item_class             text NOT NULL CHECK (item_class IN (
                               'fruit','juice','yeast','additive','packaging','consumable',
                               'intermediate','finished_good','co_product','returnable_asset')),
    base_unit_code         text NOT NULL REFERENCES app.units(code),
    lot_controlled         boolean NOT NULL DEFAULT true,
    catch_weight           boolean NOT NULL DEFAULT false,
    shelf_life_days        int,
    default_receipt_status text NOT NULL DEFAULT 'released' CHECK (default_receipt_status IN ('quarantine','released')),
    consumption_mode       text NOT NULL DEFAULT 'explicit' CHECK (consumption_mode IN ('explicit','backflush')),
    costing_method         text NOT NULL DEFAULT 'actual_lot' CHECK (costing_method IN ('actual_lot','standard')),
    standard_cost_per_base numeric(18,6),                    -- current standard; history in standard_costs
    reorder_point_base     numeric(18,4),
    min_qty_base           numeric(18,4),
    max_qty_base           numeric(18,4),
    ttb_material_category  text NOT NULL DEFAULT 'none' CHECK (ttb_material_category IN ('none','fruit','juice','concentrate','sugar','other')),
    units_per_case         int,                              -- finished goods only
    active                 boolean NOT NULL DEFAULT true,
    notes                  text,
    created_at             timestamptz NOT NULL DEFAULT now(),
    updated_at             timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX items_code_key ON app.items (lower(code));
CREATE INDEX items_class_idx ON app.items (item_class) WHERE active;
CREATE TRIGGER items_touch BEFORE UPDATE ON app.items FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

-- Per-item alternate units (25 kg sack, 40 lb bin, 48 lb bushel for this item)
CREATE TABLE app.item_units (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    item_id        bigint NOT NULL REFERENCES app.items(id) ON DELETE CASCADE,
    unit_code      text NOT NULL,                            -- free label, e.g. 'sack', 'bin', 'bushel'
    unit_name      text NOT NULL,
    to_base_factor numeric(18,8) NOT NULL,
    is_purchase_default boolean NOT NULL DEFAULT false,
    UNIQUE (item_id, unit_code)
);

-- Suppliers -----------------------------------------------------------------------------
CREATE TABLE app.suppliers (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name         text NOT NULL,
    kind         text NOT NULL DEFAULT 'vendor' CHECK (kind IN ('vendor','orchard','juice_supplier','packaging','other')),
    contact_name text,
    email        text,
    phone        text,
    address      text,
    notes        text,
    active       boolean NOT NULL DEFAULT true,
    created_at   timestamptz NOT NULL DEFAULT now(),
    updated_at   timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER suppliers_touch BEFORE UPDATE ON app.suppliers FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

CREATE TABLE app.supplier_items (
    id                 bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    supplier_id        bigint NOT NULL REFERENCES app.suppliers(id) ON DELETE CASCADE,
    item_id            bigint NOT NULL REFERENCES app.items(id),
    supplier_sku       text,
    purchase_unit_code text NOT NULL,                        -- app.units.code or item_units.unit_code
    to_base_factor     numeric(18,8) NOT NULL,
    last_price         numeric(18,4),                        -- per purchase unit
    lead_time_days     int,
    active             boolean NOT NULL DEFAULT true,
    UNIQUE (supplier_id, item_id)
);

-- Locations hold lots; vessels hold liquid ---------------------------------------------
CREATE TABLE app.locations (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    premises_id    bigint NOT NULL REFERENCES app.premises(id),
    name           text NOT NULL,
    kind           text NOT NULL CHECK (kind IN ('receiving','dry_store','cold_room','freezer','cellar','packaged_goods','taproom','outside')),
    tax_state      text NOT NULL CHECK (tax_state IN ('bonded','tax_paid')),
    allow_negative boolean NOT NULL DEFAULT false,
    active         boolean NOT NULL DEFAULT true,
    created_at     timestamptz NOT NULL DEFAULT now(),
    updated_at     timestamptz NOT NULL DEFAULT now(),
    UNIQUE (premises_id, name)
);
CREATE TRIGGER locations_touch BEFORE UPDATE ON app.locations FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

CREATE TABLE app.vessels (
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    premises_id bigint NOT NULL REFERENCES app.premises(id),
    location_id bigint NOT NULL REFERENCES app.locations(id), -- the cellar whose ledger balance it sits in
    name        text NOT NULL,
    kind        text NOT NULL CHECK (kind IN ('tank','fermenter','brite','tote','ibc','barrel','press')),
    capacity_l  numeric(14,3) NOT NULL CHECK (capacity_l > 0),
    status      text NOT NULL DEFAULT 'empty' CHECK (status IN ('empty','in_use','cleaning','out_of_service')),
    notes       text,
    active      boolean NOT NULL DEFAULT true,
    created_at  timestamptz NOT NULL DEFAULT now(),
    updated_at  timestamptz NOT NULL DEFAULT now(),
    UNIQUE (premises_id, name)
);
CREATE TRIGGER vessels_touch BEFORE UPDATE ON app.vessels FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

-- Reason codes map to TTB loss categories ----------------------------------------------
CREATE TABLE app.reason_codes (
    id                     bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    code                   text NOT NULL UNIQUE,
    name                   text NOT NULL,
    applies_to             text NOT NULL CHECK (applies_to IN ('adjustment','loss','override','count','short_close','dump')),
    ttb_category           text NOT NULL DEFAULT 'none' CHECK (ttb_category IN (
                               'none','inventory_loss','casualty_loss','testing','destroyed','breakage','shortage')),
    classification         text NOT NULL DEFAULT 'exceptional' CHECK (classification IN ('expected','exceptional')),
    requires_approval_above numeric(18,4),                   -- NULL = never. Liters for applies_to in (loss, dump);
                                                            -- the item's base unit for adjustment and count.
    active                 boolean NOT NULL DEFAULT true
);
INSERT INTO app.reason_codes (code, name, applies_to, ttb_category, classification) VALUES
    ('SAMPLE',    'Sampling and testing',          'loss',       'testing',        'expected'),
    ('LEES',      'Lees and sediment',             'loss',       'inventory_loss', 'expected'),
    ('RACK',      'Racking and transfer loss',     'loss',       'inventory_loss', 'expected'),
    ('FILTER',    'Filtration loss',               'loss',       'inventory_loss', 'expected'),
    ('PKG',       'Packaging line loss',           'loss',       'inventory_loss', 'expected'),
    ('EVAP',      'Evaporation',                   'loss',       'inventory_loss', 'expected'),
    ('SPILL',     'Spill',                         'loss',       'casualty_loss',  'exceptional'),
    ('DUMP',      'Batch dumped',                  'dump',       'destroyed',      'exceptional'),
    ('BREAK',     'Breakage',                      'loss',       'breakage',       'exceptional'),
    ('DAMAGE',    'Damaged goods',                 'adjustment', 'none',           'exceptional'),
    ('EXPIRED',   'Expired or spoiled',            'adjustment', 'destroyed',      'exceptional'),
    ('COUNT',     'Count variance',                'count',      'shortage',       'exceptional'),
    ('OPENING',   'Opening balance',               'adjustment', 'none',           'expected'),
    ('CORRECT',   'Data entry correction',         'adjustment', 'none',           'exceptional'),
    ('SHORT',     'Supplier short-shipped',        'short_close','none',           'expected'),
    ('SPECOVR',   'Spec override by quality',      'override',   'none',           'exceptional'),
    ('TAXOVR',    'Tax class override',            'override',   'none',           'exceptional');

-- Attachments on any entity --------------------------------------------------------------
CREATE TABLE app.attachments (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    entity_type  text NOT NULL,                              -- 'lot','goods_receipt','product','batch',...
    entity_id    bigint NOT NULL,
    kind         text NOT NULL CHECK (kind IN ('coa','weigh_ticket','cola','formula','lab_report','photo','other')),
    file_name    text NOT NULL,
    mime_type    text NOT NULL,
    storage_path text NOT NULL,                              -- under /var/www/storage/{client}/
    byte_size    bigint NOT NULL,
    uploaded_by  bigint REFERENCES app.users(id),
    created_at   timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX attachments_entity_idx ON app.attachments (entity_type, entity_id);
