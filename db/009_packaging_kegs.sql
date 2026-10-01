-- 009_packaging_kegs.sql — packaging runs, finished lots with tax class,
-- tax class rules, kegs as serialized assets. Slice 7.
SET search_path = app, public;

CREATE TABLE app.tax_class_rules (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    beverage_type  text NOT NULL CHECK (beverage_type IN ('cider','wine','beer')),
    effective_from date NOT NULL,
    params         jsonb NOT NULL,
    created_at     timestamptz NOT NULL DEFAULT now(),
    UNIQUE (beverage_type, effective_from)
);
-- 27 CFR 24.331 hard cider test; the fallbacks are still / artificially carbonated / sparkling wine.
INSERT INTO app.tax_class_rules (beverage_type, effective_from, params) VALUES
    ('cider', '2017-01-01', '{
        "hard_cider": {"co2_max_g_100ml": 0.64, "fruit_share_min_pct": 50, "abv_min": 0.5, "abv_max_exclusive": 8.5,
                       "other_fruit_allowed": false, "flavoring_allowed": false, "rate_per_gal": 0.226},
        "still_wine": {"co2_max_g_100ml": 0.392, "abv_max": 16, "rate_per_gal": 1.07},
        "artificially_carbonated_wine": {"rate_per_gal": 3.30},
        "sparkling_wine": {"rate_per_gal": 3.40},
        "cbma_credit_per_gal": {"hard_cider": [0.062, 0.056, 0.033], "wine": [1.00, 0.90, 0.535]},
        "cbma_tiers_gal": [30000, 100000, 620000]
    }'::jsonb);

CREATE OR REPLACE FUNCTION app.derive_tax_class(
    p_beverage_type text, p_abv numeric, p_co2 numeric, p_fruit_share_pct numeric,
    p_other_fruit boolean, p_flavoring boolean, p_naturally_sparkling boolean DEFAULT false,
    p_as_of date DEFAULT current_date)
RETURNS text LANGUAGE plpgsql STABLE AS $$
DECLARE r jsonb; hc jsonb;
BEGIN
    SELECT params INTO r FROM app.tax_class_rules
     WHERE beverage_type = p_beverage_type AND effective_from <= p_as_of
     ORDER BY effective_from DESC LIMIT 1;
    IF r IS NULL THEN RETURN NULL; END IF;
    IF p_beverage_type = 'beer' THEN RETURN 'beer'; END IF;
    hc := r->'hard_cider';
    IF p_beverage_type = 'cider' AND hc IS NOT NULL
       AND p_abv IS NOT NULL AND p_co2 IS NOT NULL AND p_fruit_share_pct IS NOT NULL
       AND p_co2 <= (hc->>'co2_max_g_100ml')::numeric
       AND p_fruit_share_pct > (hc->>'fruit_share_min_pct')::numeric
       AND p_abv >= (hc->>'abv_min')::numeric AND p_abv < (hc->>'abv_max_exclusive')::numeric
       AND NOT COALESCE(p_other_fruit, false) AND NOT COALESCE(p_flavoring, false) THEN
        RETURN 'hard_cider';
    END IF;
    IF p_co2 IS NOT NULL AND p_co2 > (r->'still_wine'->>'co2_max_g_100ml')::numeric THEN
        RETURN CASE WHEN p_naturally_sparkling THEN 'sparkling_wine' ELSE 'artificially_carbonated_wine' END;
    END IF;
    RETURN 'still_wine';
END $$;

CREATE TABLE app.packaging_runs (
    id                         bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    number                     text NOT NULL UNIQUE,
    premises_id                bigint NOT NULL REFERENCES app.premises(id),
    batch_id                   bigint NOT NULL REFERENCES app.batches(id),
    packaging_configuration_id bigint NOT NULL REFERENCES app.packaging_configurations(id),
    source_vessel_id           bigint REFERENCES app.vessels(id),
    output_location_id         bigint NOT NULL REFERENCES app.locations(id),
    run_on                     date NOT NULL DEFAULT current_date,
    started_at                 timestamptz,
    finished_at                timestamptz,
    status                     text NOT NULL DEFAULT 'draft' CHECK (status IN ('draft','posted','cancelled')),
    volume_in_l                numeric(14,3),
    units_out                  int,
    volume_out_l               numeric(14,3),
    loss_l                     numeric(14,3),
    abv_at_packaging           numeric(5,2),
    co2_g_100ml                numeric(6,3),
    notes                      text,
    created_by                 bigint REFERENCES app.users(id),
    posted_by                  bigint REFERENCES app.users(id),
    posted_at                  timestamptz,
    created_at                 timestamptz NOT NULL DEFAULT now(),
    updated_at                 timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX packaging_runs_batch_idx ON app.packaging_runs (batch_id);
CREATE TRIGGER packaging_runs_touch BEFORE UPDATE ON app.packaging_runs FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

CREATE TABLE app.packaging_run_materials (
    id               bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    packaging_run_id bigint NOT NULL REFERENCES app.packaging_runs(id) ON DELETE CASCADE,
    item_id          bigint NOT NULL REFERENCES app.items(id),
    lot_id           bigint REFERENCES app.lots(id),        -- NULL until posted for backflush
    qty_base         numeric(18,4) NOT NULL CHECK (qty_base > 0),
    mode             text NOT NULL CHECK (mode IN ('explicit','backflush'))
);

-- Finished lots: a typed extension of lots for finished goods.
CREATE TABLE app.finished_lots (
    lot_id                     bigint PRIMARY KEY REFERENCES app.lots(id) ON DELETE CASCADE,
    batch_id                   bigint NOT NULL REFERENCES app.batches(id),
    packaging_run_id           bigint NOT NULL REFERENCES app.packaging_runs(id),
    packaging_configuration_id bigint NOT NULL REFERENCES app.packaging_configurations(id),
    packaged_on                date NOT NULL,
    units_packaged             int NOT NULL CHECK (units_packaged > 0),
    unit_volume_l              numeric(10,4) NOT NULL,
    abv                        numeric(5,2),
    co2_g_100ml                numeric(6,3),
    fruit_share_pct            numeric(5,2),
    tax_class                  text NOT NULL,
    tax_class_source           text NOT NULL DEFAULT 'derived' CHECK (tax_class_source IN ('derived','override')),
    tax_class_override_reason_code_id bigint REFERENCES app.reason_codes(id),
    tax_class_override_by      bigint REFERENCES app.users(id),
    label_approval_id          bigint REFERENCES app.product_approvals(id),
    best_before_on             date,
    unit_cost                  numeric(18,6)                 -- cost per unit, filled by costing
);
CREATE INDEX finished_lots_batch_idx ON app.finished_lots (batch_id);

-- Kegs ------------------------------------------------------------------------------------
CREATE TABLE app.kegs (
    id                  bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    serial              text NOT NULL UNIQUE,
    size_l              numeric(8,3) NOT NULL CHECK (size_l > 0),
    ownership           text NOT NULL DEFAULT 'owned' CHECK (ownership IN ('owned','rented','customer_owned')),
    state               text NOT NULL DEFAULT 'empty' CHECK (state IN (
                            'empty','filled','at_customer','returned_dirty','cleaning','lost','out_of_service')),
    deposit_amount      numeric(10,2) NOT NULL DEFAULT 0,
    current_lot_id      bigint REFERENCES app.lots(id),
    current_holder_kind text NOT NULL DEFAULT 'location' CHECK (current_holder_kind IN ('location','customer','unknown')),
    current_holder_id   bigint,
    fill_count          int NOT NULL DEFAULT 0,
    last_cleaned_at     timestamptz,
    last_moved_at       timestamptz,
    notes               text,
    created_at          timestamptz NOT NULL DEFAULT now(),
    updated_at          timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX kegs_state_idx ON app.kegs (state);
CREATE TRIGGER kegs_touch BEFORE UPDATE ON app.kegs FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

CREATE TABLE app.keg_movements (
    id           bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    keg_id       bigint NOT NULL REFERENCES app.kegs(id),
    event        text NOT NULL CHECK (event IN ('fill','ship','return','clean','mark_lost','found','retire','deposit_collected','deposit_refunded')),
    lot_id       bigint REFERENCES app.lots(id),
    customer_id  bigint,                                      -- FK added in 011 after customers exists
    location_id  bigint REFERENCES app.locations(id),
    removal_id   bigint,                                      -- FK added in 011
    amount       numeric(10,2),                               -- deposit events
    occurred_at  timestamptz NOT NULL DEFAULT now(),
    actor_id     bigint REFERENCES app.users(id),
    note         text
);
CREATE INDEX keg_movements_keg_idx ON app.keg_movements (keg_id, occurred_at DESC);
