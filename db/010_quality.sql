-- 010_quality.sql — sensory records. Specs live in 007, readings and lab tests
-- in 008 (readings.is_lab), release decisions in 005. Slice 8.
SET search_path = app, public;

CREATE TABLE app.sensory_records (
    id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    target_kind   text NOT NULL CHECK (target_kind IN ('batch','lot')),
    target_id     bigint NOT NULL,
    panel_on      date NOT NULL DEFAULT current_date,
    panelist_id   bigint REFERENCES app.users(id),
    panelist_name text,
    sample_code   text,
    verdict       text NOT NULL CHECK (verdict IN ('pass','fail','hold')),
    attributes    jsonb NOT NULL DEFAULT '{}'::jsonb,       -- {"aroma": 3, "acidity": 4, ...} 1-5 intensities
    faults        jsonb NOT NULL DEFAULT '[]'::jsonb,       -- [{"fault":"acetic","intensity":2}]
    comment       text,
    created_at    timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX sensory_records_target_idx ON app.sensory_records (target_kind, target_id, panel_on DESC);

-- Evaluate a reading against the active spec for its product and stage.
CREATE OR REPLACE FUNCTION app.evaluate_reading_spec() RETURNS trigger
LANGUAGE plpgsql AS $$
DECLARE v_spec app.specs%ROWTYPE; v_product bigint;
BEGIN
    IF NEW.target_kind <> 'batch' OR NEW.stage_code IS NULL THEN RETURN NEW; END IF;
    SELECT product_id INTO v_product FROM app.batches WHERE id = NEW.target_id;
    SELECT * INTO v_spec FROM app.specs
     WHERE product_id = v_product AND stage_code = NEW.stage_code
       AND measurement_type_code = NEW.measurement_type_code AND active;
    IF NOT FOUND THEN RETURN NEW; END IF;
    NEW.spec_id := v_spec.id;
    NEW.spec_result := CASE
        WHEN (v_spec.min_value IS NOT NULL AND NEW.value < v_spec.min_value)
          OR (v_spec.max_value IS NOT NULL AND NEW.value > v_spec.max_value) THEN 'fail'
        ELSE 'pass' END;
    RETURN NEW;
END $$;
CREATE TRIGGER readings_evaluate_spec BEFORE INSERT ON app.readings
    FOR EACH ROW EXECUTE FUNCTION app.evaluate_reading_spec();
