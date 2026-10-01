-- 012_costing_views.sql — derived views for yield, cost, valuation, status.
-- Views are the contract for slice 9 screens and for the records MCP server.
SET search_path = app, public;

-- Lot balances with item and location detail, the on-hand/available answer.
CREATE OR REPLACE VIEW app.v_lot_balances AS
SELECT b.item_id, i.code AS item_code, i.name AS item_name, i.item_class, i.base_unit_code,
       b.lot_id, l.lot_number, l.quality_status, l.expires_on, l.received_on, l.unit_cost_base,
       b.location_id, loc.name AS location_name, loc.tax_state, loc.premises_id,
       b.qty_on_hand, b.qty_allocated, (b.qty_on_hand - b.qty_allocated) AS qty_available,
       (b.qty_on_hand * l.unit_cost_base) AS value_at_lot_cost
  FROM app.inventory_balances b
  JOIN app.items i ON i.id = b.item_id
  JOIN app.lots l ON l.id = b.lot_id
  JOIN app.locations loc ON loc.id = b.location_id
 WHERE b.qty_on_hand <> 0 OR b.qty_allocated <> 0;

-- Item totals across lots and locations, with reorder status.
CREATE OR REPLACE VIEW app.v_item_stock AS
SELECT i.id AS item_id, i.code, i.name, i.item_class, i.base_unit_code,
       COALESCE(sum(b.qty_on_hand), 0) AS qty_on_hand,
       COALESCE(sum(b.qty_allocated), 0) AS qty_allocated,
       COALESCE(sum(b.qty_on_hand - b.qty_allocated), 0) AS qty_available,
       COALESCE((SELECT sum(pl.qty_ordered_base - pl.qty_received_base) FROM app.purchase_order_lines pl
                  JOIN app.purchase_orders po ON po.id = pl.purchase_order_id
                 WHERE pl.item_id = i.id AND pl.status IN ('open','partial') AND po.status IN ('open','partial')), 0) AS qty_on_order,
       i.reorder_point_base, i.min_qty_base, i.max_qty_base,
       (i.reorder_point_base IS NOT NULL AND COALESCE(sum(b.qty_on_hand - b.qty_allocated), 0) < i.reorder_point_base) AS below_reorder_point
  FROM app.items i
  LEFT JOIN app.inventory_balances b ON b.item_id = i.id
 WHERE i.active
 GROUP BY i.id;

-- Inventory valuation by class and tax state (lot cost for actual-lot items, standard otherwise).
CREATE OR REPLACE VIEW app.v_inventory_valuation AS
SELECT i.item_class, loc.tax_state, loc.premises_id, i.base_unit_code,
       sum(b.qty_on_hand) AS qty_on_hand,
       sum(b.qty_on_hand * CASE WHEN i.costing_method = 'standard'
                                THEN COALESCE(i.standard_cost_per_base, 0) ELSE l.unit_cost_base END) AS value
  FROM app.inventory_balances b
  JOIN app.items i ON i.id = b.item_id
  JOIN app.lots l ON l.id = b.lot_id
  JOIN app.locations loc ON loc.id = b.location_id
 WHERE b.qty_on_hand <> 0
 GROUP BY 1,2,3,4;

-- Current vessel contents (tank board).
CREATE OR REPLACE VIEW app.v_vessel_board AS
SELECT v.id AS vessel_id, v.name AS vessel_name, v.kind AS vessel_kind, v.capacity_l, v.status AS vessel_status, v.premises_id,
       o.occupant_kind, o.occupant_id, o.volume_l, o.from_at AS occupied_since,
       CASE WHEN o.occupant_kind = 'batch' THEN b.number WHEN o.occupant_kind = 'lot' THEN l.lot_number END AS occupant_label,
       b.current_stage_code, b.product_id, p.name AS product_name,
       round(100 * o.volume_l / v.capacity_l, 1) AS fill_pct
  FROM app.vessels v
  LEFT JOIN app.vessel_occupancies o ON o.vessel_id = v.id AND o.to_at IS NULL
  LEFT JOIN app.batches b ON o.occupant_kind = 'batch' AND b.id = o.occupant_id
  LEFT JOIN app.lots l ON o.occupant_kind = 'lot' AND l.id = o.occupant_id
  LEFT JOIN app.products p ON p.id = b.product_id
 WHERE v.active;

-- Consumption cost per batch (materials at lot cost).
CREATE OR REPLACE VIEW app.v_batch_material_costs AS
SELECT c.batch_id, c.item_id, i.name AS item_name, i.item_class, c.lot_id, l.lot_number, c.purpose,
       c.qty_base, i.base_unit_code, l.unit_cost_base, (c.qty_base * l.unit_cost_base) AS cost
  FROM app.consumptions c
  JOIN app.items i ON i.id = c.item_id
  JOIN app.lots l ON l.id = c.lot_id
 WHERE c.batch_id IS NOT NULL;

-- Batch cost roll-up: materials at lot cost + packaging at standard + overhead per liter.
CREATE OR REPLACE VIEW app.v_batch_costs AS
WITH mat AS (
    SELECT batch_id, sum(cost) AS material_cost FROM app.v_batch_material_costs GROUP BY batch_id
), pkg AS (
    SELECT pr.batch_id,
           sum(m.qty_base * COALESCE(i.standard_cost_per_base, 0)) AS packaging_cost,
           sum(pr.volume_in_l) AS packaged_volume_l,
           sum(pr.units_out) AS units_out
      FROM app.packaging_runs pr
      LEFT JOIN app.packaging_run_materials m ON m.packaging_run_id = pr.id
      LEFT JOIN app.items i ON i.id = m.item_id
     WHERE pr.status = 'posted'
     GROUP BY pr.batch_id
), vol AS (
    SELECT b.id AS batch_id,
           COALESCE((SELECT max(volume_in_l) FROM app.stage_events s WHERE s.batch_id = b.id), b.current_volume_l) AS starting_volume_l
      FROM app.batches b
), oh AS (
    SELECT b.id AS batch_id,
           (SELECT rate_per_l FROM app.overhead_rates r WHERE r.premises_id = b.premises_id AND r.effective_from <= b.started_at::date
             ORDER BY r.effective_from DESC LIMIT 1) AS rate_per_l
      FROM app.batches b
)
SELECT b.id AS batch_id, b.number, b.product_id, b.recipe_version_id, b.status,
       COALESCE(mat.material_cost, 0) AS material_cost,
       COALESCE(pkg.packaging_cost, 0) AS packaging_cost,
       COALESCE(vol.starting_volume_l, 0) * COALESCE(oh.rate_per_l, 0) AS overhead_cost,
       COALESCE(mat.material_cost, 0) + COALESCE(pkg.packaging_cost, 0)
         + COALESCE(vol.starting_volume_l, 0) * COALESCE(oh.rate_per_l, 0) AS total_cost,
       vol.starting_volume_l,
       pkg.packaged_volume_l, pkg.units_out,
       CASE WHEN COALESCE(vol.starting_volume_l, 0) > 0
            THEN (COALESCE(mat.material_cost, 0) + COALESCE(vol.starting_volume_l, 0) * COALESCE(oh.rate_per_l, 0)) / vol.starting_volume_l END AS liquid_cost_per_l,
       rv.standard_cost_total,
       CASE WHEN rv.standard_cost_total IS NOT NULL
            THEN COALESCE(mat.material_cost, 0) + COALESCE(pkg.packaging_cost, 0)
                 + COALESCE(vol.starting_volume_l, 0) * COALESCE(oh.rate_per_l, 0) - rv.standard_cost_total END AS variance_to_standard
  FROM app.batches b
  LEFT JOIN mat ON mat.batch_id = b.id
  LEFT JOIN pkg ON pkg.batch_id = b.id
  LEFT JOIN vol ON vol.batch_id = b.id
  LEFT JOIN oh  ON oh.batch_id = b.id
  LEFT JOIN app.recipe_versions rv ON rv.id = b.recipe_version_id;

-- Per-stage yield against the recipe's expected loss.
CREATE OR REPLACE VIEW app.v_batch_stage_yields AS
SELECT s.batch_id, b.number, s.stage_code, st.name AS stage_name, s.entered_at, s.left_at,
       s.volume_in_l, s.volume_out_l,
       CASE WHEN s.volume_in_l > 0 AND s.volume_out_l IS NOT NULL
            THEN round(100 * (s.volume_in_l - s.volume_out_l) / s.volume_in_l, 2) END AS actual_loss_pct,
       rs.expected_loss_pct,
       (SELECT COALESCE(sum(le.qty_base), 0) FROM app.loss_events le
          WHERE le.target_kind = 'batch' AND le.target_id = s.batch_id AND le.stage_code = s.stage_code) AS recorded_loss_l
  FROM app.stage_events s
  JOIN app.batches b ON b.id = s.batch_id
  JOIN app.stages st ON st.code = s.stage_code
  LEFT JOIN app.recipe_stages rs ON rs.recipe_version_id = b.recipe_version_id AND rs.stage_code = s.stage_code;

-- Press yields by variety: gallons per ton and per bushel fall out of kg and L.
CREATE OR REPLACE VIEW app.v_press_run_yields AS
SELECT pr.id AS press_run_id, pr.number, pr.run_on, pr.premises_id,
       la.value_text AS variety,
       sum(pi.qty_kg) AS fruit_kg,
       pr.juice_l_total * (sum(pi.qty_kg) / NULLIF(pr.fruit_kg_total, 0)) AS juice_l_attributed,
       CASE WHEN sum(pi.qty_kg) > 0 THEN
            (pr.juice_l_total * (sum(pi.qty_kg) / NULLIF(pr.fruit_kg_total, 0))) / 3.785411784
            / (sum(pi.qty_kg) / 907.18474) END AS gal_per_ton,
       CASE WHEN sum(pi.qty_kg) > 0 THEN
            (pr.juice_l_total * (sum(pi.qty_kg) / NULLIF(pr.fruit_kg_total, 0))) / 3.785411784
            / (sum(pi.qty_kg) / 19.05087954) END AS gal_per_bushel
  FROM app.press_runs pr
  JOIN app.press_run_inputs pi ON pi.press_run_id = pr.id
  LEFT JOIN app.lot_attributes la ON la.lot_id = pi.lot_id AND la.key = 'variety'
 WHERE pr.status = 'posted'
 GROUP BY pr.id, la.value_text;

-- Finished goods ready to sell, by product and package.
CREATE OR REPLACE VIEW app.v_finished_stock AS
SELECT fl.lot_id, l.lot_number, fl.batch_id, b.number AS batch_number, p.id AS product_id, p.name AS product_name,
       pc.name AS package_name, pc.package_kind, fl.packaged_on, fl.tax_class, fl.abv, fl.best_before_on,
       vb.location_id, vb.location_name, vb.tax_state, vb.qty_on_hand AS units_on_hand, vb.qty_available AS units_available,
       (vb.qty_on_hand * fl.unit_volume_l) AS volume_on_hand_l, fl.unit_cost
  FROM app.finished_lots fl
  JOIN app.lots l ON l.id = fl.lot_id
  JOIN app.batches b ON b.id = fl.batch_id
  JOIN app.products p ON p.id = b.product_id
  JOIN app.packaging_configurations pc ON pc.id = fl.packaging_configuration_id
  JOIN app.v_lot_balances vb ON vb.lot_id = fl.lot_id;

-- Keg fleet summary.
CREATE OR REPLACE VIEW app.v_keg_fleet AS
SELECT k.id AS keg_id, k.serial, k.size_l, k.state, k.ownership, k.deposit_amount, k.fill_count,
       k.current_lot_id, l.lot_number, k.current_holder_kind, k.current_holder_id,
       CASE WHEN k.current_holder_kind = 'customer' THEN c.name WHEN k.current_holder_kind = 'location' THEN loc.name END AS holder_name,
       k.last_moved_at, (now()::date - k.last_moved_at::date) AS days_since_moved
  FROM app.kegs k
  LEFT JOIN app.lots l ON l.id = k.current_lot_id
  LEFT JOIN app.customers c ON k.current_holder_kind = 'customer' AND c.id = k.current_holder_id
  LEFT JOIN app.locations loc ON k.current_holder_kind = 'location' AND loc.id = k.current_holder_id;

-- Purchase order lines still open, for "what is arriving" and "what is overdue".
CREATE OR REPLACE VIEW app.v_open_po_lines AS
SELECT po.id AS purchase_order_id, po.number, po.supplier_id, s.name AS supplier_name, po.status AS po_status,
       pl.id AS line_id, pl.line_no, pl.item_id, i.name AS item_name, i.base_unit_code,
       pl.qty_ordered, pl.purchase_unit_code, pl.qty_ordered_base, pl.qty_received_base,
       (pl.qty_ordered_base - pl.qty_received_base) AS qty_outstanding_base,
       COALESCE(pl.expected_on, po.expected_on) AS expected_on,
       (COALESCE(pl.expected_on, po.expected_on) < current_date) AS overdue
  FROM app.purchase_order_lines pl
  JOIN app.purchase_orders po ON po.id = pl.purchase_order_id
  JOIN app.suppliers s ON s.id = po.supplier_id
  JOIN app.items i ON i.id = pl.item_id
 WHERE pl.status IN ('open','partial') AND po.status IN ('open','partial');

-- Supplier performance: late and short deliveries.
CREATE OR REPLACE VIEW app.v_supplier_performance AS
SELECT s.id AS supplier_id, s.name,
       count(DISTINCT gr.id) AS receipts,
       count(DISTINCT gr.id) FILTER (WHERE gr.received_at::date > COALESCE(pl.expected_on, po.expected_on)) AS late_receipts,
       count(DISTINCT grl.id) FILTER (WHERE grl.discrepancy_kind = 'short') AS short_lines,
       count(DISTINCT grl.id) FILTER (WHERE grl.discrepancy_kind = 'damaged') AS damaged_lines
  FROM app.suppliers s
  LEFT JOIN app.goods_receipts gr ON gr.supplier_id = s.id AND gr.status = 'posted'
  LEFT JOIN app.goods_receipt_lines grl ON grl.goods_receipt_id = gr.id
  LEFT JOIN app.purchase_order_lines pl ON pl.id = grl.purchase_order_line_id
  LEFT JOIN app.purchase_orders po ON po.id = pl.purchase_order_id
 GROUP BY s.id;

-- Forward trace: everything downstream of a lot (consumption -> batch -> lineage -> finished lots -> removals).
CREATE OR REPLACE FUNCTION app.trace_forward(p_lot_id bigint)
RETURNS TABLE (level int, kind text, id bigint, label text, detail jsonb)
LANGUAGE sql STABLE AS $$
WITH RECURSIVE
seed_batches AS (
    SELECT DISTINCT c.batch_id FROM app.consumptions c WHERE c.lot_id = p_lot_id AND c.batch_id IS NOT NULL
    UNION
    SELECT DISTINCT c.batch_id FROM app.press_run_outputs o
      JOIN app.consumptions c ON c.lot_id = o.lot_id AND c.batch_id IS NOT NULL
     WHERE o.press_run_id IN (SELECT press_run_id FROM app.press_run_inputs WHERE lot_id = p_lot_id)
),
batches AS (
    SELECT batch_id, 1 AS lvl FROM seed_batches
    UNION
    SELECT bl.child_batch_id, b.lvl + 1 FROM app.batch_lineage bl JOIN batches b ON bl.parent_batch_id = b.batch_id
)
SELECT b.lvl, 'batch', bt.id, bt.number, jsonb_build_object('status', bt.status, 'stage', bt.current_stage_code)
  FROM batches b JOIN app.batches bt ON bt.id = b.batch_id
UNION ALL
SELECT b.lvl + 1, 'finished_lot', fl.lot_id, l.lot_number, jsonb_build_object('packaged_on', fl.packaged_on, 'units', fl.units_packaged)
  FROM batches b JOIN app.finished_lots fl ON fl.batch_id = b.batch_id JOIN app.lots l ON l.id = fl.lot_id
UNION ALL
SELECT b.lvl + 2, 'removal', r.id, r.number, jsonb_build_object('destination', r.destination_kind, 'customer_id', r.customer_id, 'removed_at', r.removed_at, 'units', rl.units)
  FROM batches b JOIN app.finished_lots fl ON fl.batch_id = b.batch_id
  JOIN app.removal_lines rl ON rl.lot_id = fl.lot_id JOIN app.removals r ON r.id = rl.removal_id AND r.status = 'posted';
$$;

-- Backward trace: everything upstream of a finished lot or batch.
CREATE OR REPLACE FUNCTION app.trace_backward(p_batch_id bigint)
RETURNS TABLE (level int, kind text, id bigint, label text, detail jsonb)
LANGUAGE sql STABLE AS $$
WITH RECURSIVE batches AS (
    SELECT p_batch_id AS batch_id, 0 AS lvl
    UNION
    SELECT bl.parent_batch_id, b.lvl + 1 FROM app.batch_lineage bl JOIN batches b ON bl.child_batch_id = b.batch_id
)
SELECT b.lvl, 'batch', bt.id, bt.number, jsonb_build_object('status', bt.status)
  FROM batches b JOIN app.batches bt ON bt.id = b.batch_id
UNION ALL
SELECT b.lvl + 1, 'lot', l.id, l.lot_number, jsonb_build_object('item', i.name, 'supplier_lot', l.supplier_lot_number, 'purpose', c.purpose, 'qty', c.qty_base)
  FROM batches b JOIN app.consumptions c ON c.batch_id = b.batch_id JOIN app.lots l ON l.id = c.lot_id JOIN app.items i ON i.id = l.item_id
UNION ALL
SELECT b.lvl + 2, 'fruit_lot', fl.id, fl.lot_number, jsonb_build_object('item', i.name, 'press_run', pr.number, 'kg', pi.qty_kg)
  FROM batches b JOIN app.consumptions c ON c.batch_id = b.batch_id
  JOIN app.press_run_outputs o ON o.lot_id = c.lot_id
  JOIN app.press_runs pr ON pr.id = o.press_run_id
  JOIN app.press_run_inputs pi ON pi.press_run_id = pr.id
  JOIN app.lots fl ON fl.id = pi.lot_id JOIN app.items i ON i.id = fl.item_id;
$$;
