-- 007_products_recipes.sql — products, stages, measurement types, recipe
-- versions, packaging configurations, specs, standard costs, approvals. Slice 4.
SET search_path = app, public;

-- Lookups shared by recipes, batches, readings ----------------------------------------
CREATE TABLE app.stages (
    code           text PRIMARY KEY,
    name           text NOT NULL,
    beverage_types text[] NOT NULL,                        -- which beverages use it
    display_order  int NOT NULL,
    is_terminal    boolean NOT NULL DEFAULT false
);
INSERT INTO app.stages (code, name, beverage_types, display_order, is_terminal) VALUES
    ('fruit',        'Fruit received',        '{cider,wine}', 10, false),
    ('press',        'Press',                 '{cider,wine}', 20, false),
    ('juice',        'Juice in vessel',       '{cider,wine}', 30, false),
    ('juice_blend',  'Juice blend',           '{cider}',      35, false),
    ('pitch',        'Pitch',                 '{cider,wine,beer}', 40, false),
    ('primary',      'Primary fermentation',  '{cider,wine,beer}', 50, false),
    ('rack',         'Rack',                  '{cider,wine}', 60, false),
    ('maturation',   'Maturation',            '{cider,wine,beer}', 70, false),
    ('blend',        'Blend',                 '{cider,wine,beer}', 80, false),
    ('back_sweeten', 'Back-sweeten',          '{cider}',      85, false),
    ('carbonate',    'Carbonate',             '{cider,beer}', 90, false),
    ('package',      'Package',               '{cider,wine,beer}', 100, true),
    -- reserved for beer and wine (no screens yet)
    ('mash',         'Mash',                  '{beer}',       41, false),
    ('boil',         'Boil',                  '{beer}',       42, false),
    ('condition',    'Condition',             '{beer}',       71, false),
    ('barrel',       'Barrel aging',          '{wine}',       72, false);

CREATE TABLE app.measurement_types (
    code      text PRIMARY KEY,
    name      text NOT NULL,
    unit      text NOT NULL,
    decimals  int NOT NULL DEFAULT 2,
    min_valid numeric(12,4),
    max_valid numeric(12,4)
);
INSERT INTO app.measurement_types (code, name, unit, decimals, min_valid, max_valid) VALUES
    ('brix',        'Brix',                 '°Bx',       1, -5,   40),
    ('sg',          'Specific gravity',     'SG',        4, 0.98, 1.2),
    ('ph',          'pH',                   'pH',        2, 2,    5),
    ('ta',          'Titratable acidity',   'g/L',       2, 0,    20),
    ('va',          'Volatile acidity',     'g/L',       3, 0,    5),
    ('free_so2',    'Free SO2',             'mg/L',      0, 0,    200),
    ('total_so2',   'Total SO2',            'mg/L',      0, 0,    500),
    ('abv',         'Alcohol by volume',    '%',         2, 0,    25),
    ('temp_c',      'Temperature',          '°C',        1, -10,  110),
    ('co2',         'Carbon dioxide',       'g/100 mL',  3, 0,    2),
    ('co2_vol',     'CO2 volumes',          'vol',       2, 0,    6),
    ('do',          'Dissolved oxygen',     'ppb',       0, 0,    5000),
    ('cell_count',  'Cell count',           'M cells/mL',1, 0,    1000),
    ('viability',   'Yeast viability',      '%',         1, 0,    100),
    ('rs',          'Residual sugar',       'g/L',       1, 0,    300),
    ('yan',         'Yeast assimilable N',  'mg/L',      0, 0,    600),
    ('malic',       'Malic acid',           'g/L',       2, 0,    15),
    ('pressure_psi','Pressure',             'psi',       1, 0,    60),
    ('volume_l',    'Volume',               'L',         1, 0,    1000000);

-- Products --------------------------------------------------------------------------
CREATE TABLE app.products (
    id                     bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    code                   text NOT NULL,
    name                   text NOT NULL,
    beverage_type          text NOT NULL DEFAULT 'cider' CHECK (beverage_type IN ('cider','wine','beer')),
    style                  text,
    intended_tax_class     text NOT NULL DEFAULT 'hard_cider' CHECK (intended_tax_class IN (
                               'hard_cider','still_wine','artificially_carbonated_wine','sparkling_wine','beer')),
    target_abv             numeric(5,2),
    target_fruit_share_pct numeric(5,2) DEFAULT 100,
    contains_other_fruit   boolean NOT NULL DEFAULT false,  -- any fruit other than apple/pear
    contains_flavoring     boolean NOT NULL DEFAULT false,  -- flavors beyond the hard-cider allowance
    status                 text NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','active','retired')),
    notes                  text,
    created_at             timestamptz NOT NULL DEFAULT now(),
    updated_at             timestamptz NOT NULL DEFAULT now()
);
CREATE UNIQUE INDEX products_code_key ON app.products (lower(code));
CREATE TRIGGER products_touch BEFORE UPDATE ON app.products FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

-- Recipe versions: immutable once active -------------------------------------------
CREATE TABLE app.recipe_versions (
    id                     bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    product_id             bigint NOT NULL REFERENCES app.products(id),
    version_no             int NOT NULL,
    status                 text NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','active','retired')),
    target_batch_volume_l  numeric(14,3) NOT NULL CHECK (target_batch_volume_l > 0),
    expected_total_loss_pct numeric(5,2),                   -- cached sum of stage losses
    standard_cost_total    numeric(18,4),                    -- snapshot at activation
    standard_cost_per_l    numeric(18,6),
    change_note            text,
    activated_at           timestamptz,
    activated_by           bigint REFERENCES app.users(id),
    created_by             bigint REFERENCES app.users(id),
    created_at             timestamptz NOT NULL DEFAULT now(),
    updated_at             timestamptz NOT NULL DEFAULT now(),
    UNIQUE (product_id, version_no)
);
CREATE UNIQUE INDEX recipe_versions_one_active ON app.recipe_versions (product_id) WHERE status = 'active';
CREATE TRIGGER recipe_versions_touch BEFORE UPDATE ON app.recipe_versions FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

CREATE OR REPLACE FUNCTION app.recipe_guard_immutable() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE v_status text;
BEGIN
    SELECT status INTO v_status FROM app.recipe_versions WHERE id = COALESCE(NEW.recipe_version_id, OLD.recipe_version_id);
    IF v_status <> 'draft' THEN
        RAISE EXCEPTION 'recipe version is %, create a new version to change it', v_status
            USING ERRCODE = 'integrity_constraint_violation';
    END IF;
    RETURN COALESCE(NEW, OLD);
END $$;

CREATE TABLE app.recipe_stages (
    id                    bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    recipe_version_id     bigint NOT NULL REFERENCES app.recipe_versions(id) ON DELETE CASCADE,
    seq                   int NOT NULL,
    stage_code            text NOT NULL REFERENCES app.stages(code),
    expected_loss_pct     numeric(5,2) NOT NULL DEFAULT 0,
    expected_duration_days int,
    instructions          text,
    UNIQUE (recipe_version_id, seq)
);
CREATE TRIGGER recipe_stages_guard BEFORE INSERT OR UPDATE OR DELETE ON app.recipe_stages
    FOR EACH ROW EXECUTE FUNCTION app.recipe_guard_immutable();

CREATE TABLE app.recipe_lines (
    id                bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    recipe_version_id bigint NOT NULL REFERENCES app.recipe_versions(id) ON DELETE CASCADE,
    seq               int NOT NULL,
    item_id           bigint NOT NULL REFERENCES app.items(id),
    stage_code        text NOT NULL REFERENCES app.stages(code),
    purpose           text NOT NULL CHECK (purpose IN ('base_juice','yeast','nutrient','sulfite','enzyme','sweetener','acid','fining','other')),
    qty_per_batch_base numeric(18,4),                        -- fixed per batch, or
    qty_per_l         numeric(18,8),                         -- scales with volume
    consumption_mode  text NOT NULL DEFAULT 'explicit' CHECK (consumption_mode IN ('explicit','backflush')),
    notes             text,
    UNIQUE (recipe_version_id, seq),
    CHECK ((qty_per_batch_base IS NOT NULL) <> (qty_per_l IS NOT NULL))
);
CREATE TRIGGER recipe_lines_guard BEFORE INSERT OR UPDATE OR DELETE ON app.recipe_lines
    FOR EACH ROW EXECUTE FUNCTION app.recipe_guard_immutable();

-- Specs: acceptable ranges per product per stage ---------------------------------------
CREATE TABLE app.specs (
    id                    bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    product_id            bigint NOT NULL REFERENCES app.products(id) ON DELETE CASCADE,
    stage_code            text NOT NULL REFERENCES app.stages(code),
    measurement_type_code text NOT NULL REFERENCES app.measurement_types(code),
    min_value             numeric(12,4),
    max_value             numeric(12,4),
    target_value          numeric(12,4),
    active                boolean NOT NULL DEFAULT true,
    UNIQUE (product_id, stage_code, measurement_type_code),
    CHECK (min_value IS NOT NULL OR max_value IS NOT NULL)
);

-- Packaging configurations ------------------------------------------------------------
CREATE TABLE app.packaging_configurations (
    id                bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    product_id        bigint NOT NULL REFERENCES app.products(id),
    finished_item_id  bigint NOT NULL REFERENCES app.items(id),  -- item_class finished_good
    name              text NOT NULL,
    package_kind      text NOT NULL CHECK (package_kind IN ('keg','can','bottle')),
    fill_volume_l     numeric(10,4) NOT NULL CHECK (fill_volume_l > 0),  -- per unit (keg or can)
    units_per_case    int,
    expected_loss_pct numeric(5,2) NOT NULL DEFAULT 2,
    active            boolean NOT NULL DEFAULT true,
    created_at        timestamptz NOT NULL DEFAULT now(),
    updated_at        timestamptz NOT NULL DEFAULT now(),
    UNIQUE (product_id, finished_item_id)
);
CREATE TRIGGER packaging_configurations_touch BEFORE UPDATE ON app.packaging_configurations FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

CREATE TABLE app.packaging_bom_lines (
    id                bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    configuration_id  bigint NOT NULL REFERENCES app.packaging_configurations(id) ON DELETE CASCADE,
    item_id           bigint NOT NULL REFERENCES app.items(id),
    qty_per_unit_base numeric(18,6) NOT NULL CHECK (qty_per_unit_base > 0),
    UNIQUE (configuration_id, item_id)
);

-- Standard costs and overhead ---------------------------------------------------------
CREATE TABLE app.standard_costs (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    item_id        bigint NOT NULL REFERENCES app.items(id) ON DELETE CASCADE,
    cost_per_base  numeric(18,6) NOT NULL CHECK (cost_per_base >= 0),
    effective_from date NOT NULL DEFAULT current_date,
    created_by     bigint REFERENCES app.users(id),
    created_at     timestamptz NOT NULL DEFAULT now(),
    UNIQUE (item_id, effective_from)
);

CREATE TABLE app.overhead_rates (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    premises_id    bigint NOT NULL REFERENCES app.premises(id),
    rate_per_l     numeric(18,6) NOT NULL CHECK (rate_per_l >= 0),
    effective_from date NOT NULL DEFAULT current_date,
    created_by     bigint REFERENCES app.users(id),
    created_at     timestamptz NOT NULL DEFAULT now(),
    UNIQUE (premises_id, effective_from)
);

-- Regulatory approvals per product -----------------------------------------------------
CREATE TABLE app.product_approvals (
    id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    product_id    bigint NOT NULL REFERENCES app.products(id) ON DELETE CASCADE,
    kind          text NOT NULL CHECK (kind IN ('formula','label')),
    packaging_configuration_id bigint REFERENCES app.packaging_configurations(id),  -- label: which package
    reference_no  text,                                      -- TTB formula id or COLA id
    status        text NOT NULL DEFAULT 'not_required' CHECK (status IN ('not_required','required','submitted','approved','expired','rejected')),
    approved_on   date,
    expires_on    date,
    attachment_id bigint REFERENCES app.attachments(id),
    notes         text,
    created_at    timestamptz NOT NULL DEFAULT now(),
    updated_at    timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER product_approvals_touch BEFORE UPDATE ON app.product_approvals FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();
