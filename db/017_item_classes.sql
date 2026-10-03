-- 017_item_classes.sql — item classes become data (app.item_classes) so a client can add
-- material types and rename the built-in ones. Built-in codes carry behaviour in PHP (fruit is
-- catch-weight and uses the fruit display unit, finished_good is the Finished product screen,
-- packaging feeds the packaging BOM), so a built-in row cannot be deleted and its code and kind
-- cannot change. Custom classes behave by their flags: kind picks the On hand screen,
-- purchasable offers the class on purchase orders and receipts, recipe_ingredient on recipe
-- lines and batch additions.
SET search_path = app, public;

CREATE TABLE app.item_classes (
    id                bigint GENERATED ALWAYS AS IDENTITY UNIQUE,
    code              text PRIMARY KEY CHECK (code ~ '^[a-z][a-z0-9_]{1,29}$'),
    name              text NOT NULL,
    kind              text NOT NULL CHECK (kind IN ('material','finished')),
    purchasable       boolean NOT NULL DEFAULT true,
    recipe_ingredient boolean NOT NULL DEFAULT false,
    display_order     int NOT NULL DEFAULT 100,
    is_builtin        boolean NOT NULL DEFAULT false,
    active            boolean NOT NULL DEFAULT true,
    notes             text,
    created_at        timestamptz NOT NULL DEFAULT now(),
    updated_at        timestamptz NOT NULL DEFAULT now()
);
CREATE TRIGGER item_classes_touch BEFORE UPDATE ON app.item_classes FOR EACH ROW EXECUTE FUNCTION app.touch_updated_at();

INSERT INTO app.item_classes (code, name, kind, purchasable, recipe_ingredient, display_order, is_builtin) VALUES
    ('fruit',            'Fruit',            'material', true,  true,  10,  true),
    ('juice',            'Juice',            'material', true,  true,  20,  true),
    ('yeast',            'Yeast',            'material', true,  true,  30,  true),
    ('additive',         'Additive',         'material', true,  true,  40,  true),
    ('packaging',        'Packaging',        'material', true,  false, 50,  true),
    ('consumable',       'Consumable',       'material', true,  true,  60,  true),
    ('intermediate',     'Intermediate',     'material', false, true,  70,  true),
    ('finished_good',    'Finished good',    'finished', false, false, 80,  true),
    ('co_product',       'Co-product',       'material', false, false, 90,  true),
    ('returnable_asset', 'Returnable asset', 'material', true,  false, 100, true);

CREATE OR REPLACE FUNCTION app.item_classes_guard_builtin() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        IF OLD.is_builtin THEN
            RAISE EXCEPTION 'built-in item class % cannot be deleted', OLD.code USING ERRCODE = 'integrity_constraint_violation';
        END IF;
        RETURN OLD;
    END IF;
    IF OLD.is_builtin AND (NEW.code <> OLD.code OR NEW.kind <> OLD.kind OR NOT NEW.is_builtin) THEN
        RAISE EXCEPTION 'built-in item class %: the code and kind cannot change', OLD.code USING ERRCODE = 'integrity_constraint_violation';
    END IF;
    IF NOT OLD.is_builtin THEN
        NEW.is_builtin := false;
    END IF;
    RETURN NEW;
END $$;
CREATE TRIGGER item_classes_guard BEFORE UPDATE OR DELETE ON app.item_classes FOR EACH ROW EXECUTE FUNCTION app.item_classes_guard_builtin();

-- items.item_class now references the table; renaming a custom code follows through.
ALTER TABLE app.items DROP CONSTRAINT items_item_class_check,
    ADD CONSTRAINT items_item_class_fkey FOREIGN KEY (item_class) REFERENCES app.item_classes(code) ON UPDATE CASCADE;
