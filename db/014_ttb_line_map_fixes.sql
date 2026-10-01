-- 014_ttb_line_map_fixes.sql — data fixes found while building slice 10.
-- Destroyed bulk wine (a dumped batch) is a loss other than inventory on 5120.17
-- Section A line 29; the seed only mapped casualty losses there.
SET search_path = app, public;

INSERT INTO app.ttb_line_map (form_code, section, line_code, label, source, match, sign, display_order)
VALUES ('5120.17', 'A', '29d', 'Losses other than inventory (destroyed)', 'loss', '{"ttb_category":"destroyed","bulk":true}', 1, 291)
ON CONFLICT (form_code, section, line_code) DO NOTHING;

-- A5 "Blending (in)" only moves wine between tax classes. Batch blends stay in one
-- class and net to zero, so the line stays zero until cross-class blends are tracked.
UPDATE app.ttb_line_map SET label = 'Blending (in) — cross-tax-class blends only'
WHERE form_code = '5120.17' AND section = 'A' AND line_code = '5';
