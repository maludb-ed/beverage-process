"""SQL for every records tool. All statements are static and read-only; optional filters
are nullable named parameters (CAST(%(x)s AS type) IS NULL OR ...). Dates are compared in
the client's time zone (%(tz)s). List queries take %(lim)s = limit + 1 and %(off)s."""

# ---------------------------------------------------------------------------- receiving

OPEN_ORDERS = """
SELECT v.number AS po_number, v.po_status, v.supplier_name, v.line_no, i.code AS item_code, v.item_name, i.item_class,
       v.base_unit_code, v.qty_ordered, v.purchase_unit_code, v.qty_ordered_base, v.qty_received_base, v.qty_outstanding_base,
       v.expected_on, po.ordered_on,
       (v.expected_on < (now() AT TIME ZONE %(tz)s)::date) AS overdue,
       CASE WHEN v.expected_on < (now() AT TIME ZONE %(tz)s)::date THEN (now() AT TIME ZONE %(tz)s)::date - v.expected_on END AS days_overdue
  FROM app.v_open_po_lines v
  JOIN app.items i ON i.id = v.item_id
  JOIN app.purchase_orders po ON po.id = v.purchase_order_id
 WHERE (CAST(%(supplier_id)s AS bigint) IS NULL OR v.supplier_id = %(supplier_id)s)
   AND (CAST(%(item_id)s AS bigint) IS NULL OR v.item_id = %(item_id)s)
   AND (CAST(%(date_from)s AS date) IS NULL OR v.expected_on >= %(date_from)s)
   AND (CAST(%(date_to)s AS date) IS NULL OR v.expected_on <= %(date_to)s)
   AND (NOT %(overdue_only)s OR v.expected_on < (now() AT TIME ZONE %(tz)s)::date)
 ORDER BY v.expected_on NULLS LAST, v.number, v.line_no
 LIMIT %(lim)s OFFSET %(off)s
"""

RECEIPT_LINES = """
SELECT gr.number AS receipt_number, gr.status AS receipt_status, gr.received_at, gr.delivery_note_ref, s.name AS supplier_name,
       po.number AS po_number, grl.line_no, i.code AS item_code, i.name AS item_name, i.item_class, i.base_unit_code,
       pl.line_no AS po_line_no, pl.qty_ordered, pl.purchase_unit_code AS po_unit, pl.qty_ordered_base,
       pl.qty_received_base AS po_line_received_base, pl.status AS po_line_status,
       grl.qty_received, grl.purchase_unit_code, grl.qty_base, grl.discrepancy_kind, grl.discrepancy_note, l.lot_number,
       ur.display_name AS received_by, up.display_name AS posted_by
  FROM app.goods_receipt_lines grl
  JOIN app.goods_receipts gr ON gr.id = grl.goods_receipt_id
  JOIN app.suppliers s ON s.id = gr.supplier_id
  JOIN app.items i ON i.id = grl.item_id
  LEFT JOIN app.purchase_order_lines pl ON pl.id = grl.purchase_order_line_id
  LEFT JOIN app.purchase_orders po ON po.id = COALESCE(pl.purchase_order_id, gr.purchase_order_id)
  LEFT JOIN app.lots l ON l.id = grl.lot_id
  LEFT JOIN app.users ur ON ur.id = gr.received_by
  LEFT JOIN app.users up ON up.id = gr.posted_by
 WHERE (CAST(%(receipt_id)s AS bigint) IS NULL OR gr.id = %(receipt_id)s)
   AND (CAST(%(po_id)s AS bigint) IS NULL OR gr.purchase_order_id = %(po_id)s OR pl.purchase_order_id = %(po_id)s)
 ORDER BY gr.received_at, gr.number, grl.line_no
"""

PO_LINES = """
SELECT po.number AS po_number, po.status AS po_status, po.ordered_on, po.expected_on AS po_expected_on, s.name AS supplier_name,
       pl.line_no, i.code AS item_code, i.name AS item_name, i.item_class, i.base_unit_code, pl.qty_ordered, pl.purchase_unit_code,
       pl.qty_ordered_base, pl.qty_received_base, (pl.qty_ordered_base - pl.qty_received_base) AS qty_outstanding_base,
       pl.status AS line_status, rc.name AS close_reason, pl.unit_price
  FROM app.purchase_order_lines pl
  JOIN app.purchase_orders po ON po.id = pl.purchase_order_id
  JOIN app.suppliers s ON s.id = po.supplier_id
  JOIN app.items i ON i.id = pl.item_id
  LEFT JOIN app.reason_codes rc ON rc.id = pl.close_reason_code_id
 WHERE po.id = %(po_id)s
 ORDER BY pl.line_no
"""

RECEIPT_LOTS = """
SELECT grl.line_no, i.code AS item_code, i.name AS item_name, i.item_class, i.base_unit_code, grl.qty_base,
       l.lot_number, COALESCE(l.supplier_lot_number, grl.supplier_lot_number) AS supplier_lot_number,
       COALESCE(l.expires_on, grl.expires_on) AS expires_on, l.quality_status,
       (SELECT jsonb_agg(jsonb_build_object('issued_on', c.issued_on, 'issuer', c.issuer, 'values', c.values_json, 'file', a.file_name) ORDER BY c.id)
          FROM app.certificates_of_analysis c LEFT JOIN app.attachments a ON a.id = c.attachment_id WHERE c.lot_id = l.id) AS certificates,
       (SELECT jsonb_agg(a.file_name ORDER BY a.id) FROM app.attachments a WHERE a.kind = 'coa' AND a.entity_type = 'lot' AND a.entity_id = l.id) AS coa_files,
       (SELECT jsonb_object_agg(la.key, jsonb_build_object('value', COALESCE(to_jsonb(la.value_num), to_jsonb(la.value_text)), 'unit', la.unit_code, 'source', la.source))
          FROM app.lot_attributes la WHERE la.lot_id = l.id) AS attributes,
       (SELECT jsonb_build_object('tag', w.tag_number, 'net_kg', w.net_kg, 'bins', w.bin_count, 'variety', w.variety, 'orchard', w.orchard, 'block', w.block, 'brix', w.brix_at_receipt)
          FROM app.weigh_tags w WHERE w.goods_receipt_line_id = grl.id) AS weigh_tag
  FROM app.goods_receipt_lines grl
  JOIN app.items i ON i.id = grl.item_id
  LEFT JOIN app.lots l ON l.id = grl.lot_id
 WHERE grl.goods_receipt_id = %(receipt_id)s
 ORDER BY grl.line_no
"""

RECEIPT_HEADER = """
SELECT gr.number, gr.status, gr.received_at, gr.posted_at, gr.delivery_note_ref, s.name AS supplier_name, po.number AS po_number,
       loc.name AS receiving_location,
       (SELECT jsonb_agg(jsonb_build_object('kind', a.kind, 'file', a.file_name) ORDER BY a.id) FROM app.attachments a
         WHERE a.entity_type IN ('goods_receipt', 'receipt') AND a.entity_id = gr.id) AS receipt_attachments
  FROM app.goods_receipts gr
  JOIN app.suppliers s ON s.id = gr.supplier_id
  LEFT JOIN app.purchase_orders po ON po.id = gr.purchase_order_id
  LEFT JOIN app.locations loc ON loc.id = gr.receiving_location_id
 WHERE gr.id = %(receipt_id)s
"""

LOT_DETAIL = """
SELECT l.id, l.lot_number, i.code AS item_code, i.name AS item_name, i.item_class, i.base_unit_code, l.quality_status,
       l.supplier_lot_number, s.name AS supplier_name, l.received_on, l.produced_on, l.expires_on, l.source_kind, l.unit_cost_base,
       COALESCE((SELECT sum(b.qty_on_hand) FROM app.inventory_balances b WHERE b.lot_id = l.id), 0) AS qty_on_hand,
       fl.unit_volume_l, fb.number AS finished_from_batch
  FROM app.lots l
  JOIN app.items i ON i.id = l.item_id
  LEFT JOIN app.suppliers s ON s.id = l.supplier_id
  LEFT JOIN app.finished_lots fl ON fl.lot_id = l.id
  LEFT JOIN app.batches fb ON fb.id = fl.batch_id
 WHERE l.id = %(lot_id)s
"""

RELEASE_DECISIONS = """
SELECT d.decided_at, d.from_status, d.to_status, d.basis, d.is_override, rc.code AS reason_code, rc.name AS reason_name, d.note,
       u.display_name AS decided_by
  FROM app.release_decisions d
  JOIN app.users u ON u.id = d.decided_by
  LEFT JOIN app.reason_codes rc ON rc.id = d.reason_code_id
 WHERE d.target_kind = %(kind)s AND d.target_id = %(target_id)s
 ORDER BY d.decided_at DESC, d.id DESC
 LIMIT %(lim)s
"""

PRICE_HISTORY = """
SELECT CASE %(group_by)s
         WHEN 'year' THEN to_char(gr.received_at AT TIME ZONE %(tz)s, 'YYYY')
         WHEN 'month' THEN to_char(gr.received_at AT TIME ZONE %(tz)s, 'YYYY-MM')
         ELSE gr.number END AS period,
       min((gr.received_at AT TIME ZONE %(tz)s)::date) AS first_received_on,
       max((gr.received_at AT TIME ZONE %(tz)s)::date) AS last_received_on,
       s.name AS supplier_name, i.code AS item_code, i.name AS item_name, i.item_class, i.base_unit_code,
       count(*) AS receipt_lines, sum(grl.qty_base) AS qty_base, sum(grl.qty_base * grl.unit_cost_base) AS total_cost,
       sum(grl.qty_base * grl.unit_cost_base) / NULLIF(sum(grl.qty_base), 0) AS avg_cost_per_base,
       min(grl.unit_cost_base) AS min_cost_per_base, max(grl.unit_cost_base) AS max_cost_per_base
  FROM app.goods_receipt_lines grl
  JOIN app.goods_receipts gr ON gr.id = grl.goods_receipt_id AND gr.status = 'posted'
  JOIN app.suppliers s ON s.id = gr.supplier_id
  JOIN app.items i ON i.id = grl.item_id
 WHERE (CAST(%(item_id)s AS bigint) IS NULL OR grl.item_id = %(item_id)s)
   AND (CAST(%(item_class)s AS text) IS NULL OR i.item_class = %(item_class)s)
   AND (CAST(%(supplier_id)s AS bigint) IS NULL OR gr.supplier_id = %(supplier_id)s)
   AND (CAST(%(date_from)s AS date) IS NULL OR (gr.received_at AT TIME ZONE %(tz)s)::date >= %(date_from)s)
   AND (CAST(%(date_to)s AS date) IS NULL OR (gr.received_at AT TIME ZONE %(tz)s)::date <= %(date_to)s)
 GROUP BY 1, s.name, i.code, i.name, i.item_class, i.base_unit_code
 ORDER BY 1, i.code, s.name
 LIMIT %(lim)s OFFSET %(off)s
"""

SUPPLIER_PERFORMANCE = """
WITH lines AS (
    SELECT gr.supplier_id, gr.id AS receipt_id, grl.id AS line_id, grl.discrepancy_kind,
           ((gr.received_at AT TIME ZONE %(tz)s)::date > COALESCE(pl.expected_on, po.expected_on)) AS late,
           (gr.received_at AT TIME ZONE %(tz)s)::date - COALESCE(pl.expected_on, po.expected_on) AS days_late
      FROM app.goods_receipts gr
      LEFT JOIN app.goods_receipt_lines grl ON grl.goods_receipt_id = gr.id
      LEFT JOIN app.purchase_order_lines pl ON pl.id = grl.purchase_order_line_id
      LEFT JOIN app.purchase_orders po ON po.id = COALESCE(pl.purchase_order_id, gr.purchase_order_id)
     WHERE gr.status = 'posted'
       AND (CAST(%(date_from)s AS date) IS NULL OR (gr.received_at AT TIME ZONE %(tz)s)::date >= %(date_from)s)
       AND (CAST(%(date_to)s AS date) IS NULL OR (gr.received_at AT TIME ZONE %(tz)s)::date <= %(date_to)s)
), overdue AS (
    SELECT supplier_id, count(*) AS overdue_open_lines FROM app.v_open_po_lines
     WHERE expected_on < (now() AT TIME ZONE %(tz)s)::date GROUP BY supplier_id
)
SELECT s.name AS supplier_name, s.kind,
       count(DISTINCT l.receipt_id) AS receipts,
       count(DISTINCT l.receipt_id) FILTER (WHERE l.late) AS late_receipts,
       max(l.days_late) FILTER (WHERE l.late) AS worst_days_late,
       count(DISTINCT l.line_id) AS lines,
       count(DISTINCT l.line_id) FILTER (WHERE l.discrepancy_kind = 'short') AS short_lines,
       count(DISTINCT l.line_id) FILTER (WHERE l.discrepancy_kind = 'over') AS over_lines,
       count(DISTINCT l.line_id) FILTER (WHERE l.discrepancy_kind = 'damaged') AS damaged_lines,
       count(DISTINCT l.line_id) FILTER (WHERE l.discrepancy_kind = 'substituted') AS substituted_lines,
       COALESCE(o.overdue_open_lines, 0) AS overdue_open_lines
  FROM app.suppliers s
  LEFT JOIN lines l ON l.supplier_id = s.id
  LEFT JOIN overdue o ON o.supplier_id = s.id
 WHERE (CAST(%(supplier_id)s AS bigint) IS NULL OR s.id = %(supplier_id)s)
 GROUP BY s.id, s.name, s.kind, o.overdue_open_lines
 ORDER BY (count(DISTINCT l.receipt_id) FILTER (WHERE l.late)
           + count(DISTINCT l.line_id) FILTER (WHERE l.discrepancy_kind IN ('short','damaged'))) DESC, s.name
 LIMIT %(lim)s OFFSET %(off)s
"""

FRUIT_INTAKE = """
SELECT extract(year FROM gr.received_at AT TIME ZONE %(tz)s)::int AS season_year,
       COALESCE(w.variety, va.value_text, i.name) AS variety, COALESCE(w.orchard, s.name) AS orchard,
       count(*) AS weigh_tags, sum(w.net_kg) AS net_kg, sum(w.bin_count) AS bins,
       sum(w.net_kg * w.brix_at_receipt) FILTER (WHERE w.brix_at_receipt IS NOT NULL)
         / NULLIF(sum(w.net_kg) FILTER (WHERE w.brix_at_receipt IS NOT NULL), 0) AS brix_weighted,
       min(w.brix_at_receipt) AS brix_min, max(w.brix_at_receipt) AS brix_max,
       jsonb_agg(DISTINCT l.lot_number) FILTER (WHERE l.lot_number IS NOT NULL) AS lot_numbers,
       jsonb_agg(DISTINCT s.name) AS suppliers,
       min((gr.received_at AT TIME ZONE %(tz)s)::date) AS first_received_on, max((gr.received_at AT TIME ZONE %(tz)s)::date) AS last_received_on
  FROM app.weigh_tags w
  JOIN app.goods_receipt_lines grl ON grl.id = w.goods_receipt_line_id
  JOIN app.goods_receipts gr ON gr.id = grl.goods_receipt_id AND gr.status = 'posted'
  JOIN app.suppliers s ON s.id = gr.supplier_id
  JOIN app.items i ON i.id = grl.item_id
  LEFT JOIN app.lots l ON l.id = grl.lot_id
  LEFT JOIN app.lot_attributes va ON va.lot_id = l.id AND va.key = 'variety'
 WHERE (CAST(%(season_year)s AS int) IS NULL OR extract(year FROM gr.received_at AT TIME ZONE %(tz)s) = %(season_year)s)
   AND (CAST(%(variety_pat)s AS text) IS NULL OR COALESCE(w.variety, va.value_text, i.name) ILIKE %(variety_pat)s)
   AND (CAST(%(orchard_pat)s AS text) IS NULL OR COALESCE(w.orchard, s.name) ILIKE %(orchard_pat)s)
 GROUP BY 1, 2, 3
 ORDER BY 1 DESC, sum(w.net_kg) DESC
 LIMIT %(lim)s OFFSET %(off)s
"""

# ---------------------------------------------------------------------------- inventory

ON_HAND = """
SELECT i.code AS item_code, i.name AS item_name, i.item_class, i.base_unit_code, l.lot_number, l.quality_status,
       l.expires_on, COALESCE(l.received_on, l.produced_on) AS received_on, loc.name AS location_name, loc.tax_state,
       (SELECT a.name FROM app.locations a WHERE a.id = loc.parent_location_id) AS rack_area, loc.rack_number,
       b.qty_on_hand, b.qty_allocated, (b.qty_on_hand - b.qty_allocated) AS qty_available,
       b.qty_on_hand * CASE WHEN i.costing_method = 'standard' THEN COALESCE(i.standard_cost_per_base, 0) ELSE l.unit_cost_base END AS value,
       fl.unit_volume_l
  FROM app.inventory_balances b
  JOIN app.items i ON i.id = b.item_id
  JOIN app.lots l ON l.id = b.lot_id
  JOIN app.locations loc ON loc.id = b.location_id
  LEFT JOIN app.finished_lots fl ON fl.lot_id = l.id
 WHERE (%(include_zero)s OR b.qty_on_hand <> 0 OR b.qty_allocated <> 0)
   AND (CAST(%(item_id)s AS bigint) IS NULL OR b.item_id = %(item_id)s)
   AND (CAST(%(item_class)s AS text) IS NULL OR i.item_class = %(item_class)s)
   AND (CAST(%(location_id)s AS bigint) IS NULL OR b.location_id = %(location_id)s
        OR b.location_id IN (SELECT r.id FROM app.locations r WHERE r.parent_location_id = %(location_id)s))
   AND (CAST(%(lot_id)s AS bigint) IS NULL OR b.lot_id = %(lot_id)s)
   AND (CAST(%(quality_status)s AS text) IS NULL OR l.quality_status = %(quality_status)s)
 ORDER BY i.code, l.expires_on NULLS LAST, l.lot_number, loc.name
 LIMIT %(lim)s OFFSET %(off)s
"""

ON_HAND_TOTALS = """
SELECT i.code AS item_code, i.name AS item_name, i.item_class, i.base_unit_code,
       sum(b.qty_on_hand) AS qty_on_hand, sum(b.qty_on_hand) FILTER (WHERE l.quality_status = 'released') AS qty_released,
       sum(b.qty_allocated) AS qty_allocated, count(DISTINCT b.lot_id) FILTER (WHERE b.qty_on_hand <> 0) AS lots,
       sum(b.qty_on_hand * CASE WHEN i.costing_method = 'standard' THEN COALESCE(i.standard_cost_per_base, 0) ELSE l.unit_cost_base END) AS value,
       sum(b.qty_on_hand * fl.unit_volume_l) AS volume_l
  FROM app.inventory_balances b
  JOIN app.items i ON i.id = b.item_id
  JOIN app.lots l ON l.id = b.lot_id
  LEFT JOIN app.finished_lots fl ON fl.lot_id = l.id
 WHERE (b.qty_on_hand <> 0 OR b.qty_allocated <> 0)
   AND (CAST(%(item_id)s AS bigint) IS NULL OR b.item_id = %(item_id)s)
   AND (CAST(%(item_class)s AS text) IS NULL OR i.item_class = %(item_class)s)
   AND (CAST(%(location_id)s AS bigint) IS NULL OR b.location_id = %(location_id)s
        OR b.location_id IN (SELECT r.id FROM app.locations r WHERE r.parent_location_id = %(location_id)s))
   AND (CAST(%(lot_id)s AS bigint) IS NULL OR b.lot_id = %(lot_id)s)
   AND (CAST(%(quality_status)s AS text) IS NULL OR l.quality_status = %(quality_status)s)
 GROUP BY i.id, i.code, i.name, i.item_class, i.base_unit_code
 ORDER BY i.code
 LIMIT 200
"""

AVAILABLE_AFTER_ORDERS = """
WITH stock AS (
    SELECT b.item_id, sum(b.qty_on_hand) AS on_hand,
           COALESCE(sum(b.qty_on_hand - b.qty_allocated) FILTER (WHERE l.quality_status = 'released'), 0) AS released_available
      FROM app.inventory_balances b JOIN app.lots l ON l.id = b.lot_id GROUP BY b.item_id
), alloc AS (
    SELECT a.item_id, sum(a.qty_base) AS qty,
           jsonb_agg(jsonb_build_object('order', po.number, 'status', po.status, 'planned_pitch_on', po.planned_pitch_on, 'qty_base', a.qty_base)
                     ORDER BY po.planned_pitch_on NULLS LAST, po.number) AS orders
      FROM app.allocations a JOIN app.production_orders po ON po.id = a.production_order_id
     WHERE a.released_at IS NULL AND po.status IN ('planned','released','in_progress')
     GROUP BY a.item_id
), req AS (
    SELECT rl.item_id, sum(COALESCE(rl.qty_per_batch_base, rl.qty_per_l * po.planned_volume_l)) AS qty,
           jsonb_agg(jsonb_build_object('order', po.number, 'status', po.status, 'planned_pitch_on', po.planned_pitch_on,
                     'qty_base', round(COALESCE(rl.qty_per_batch_base, rl.qty_per_l * po.planned_volume_l), 4))
                     ORDER BY po.planned_pitch_on NULLS LAST, po.number) AS orders
      FROM app.production_orders po JOIN app.recipe_lines rl ON rl.recipe_version_id = po.recipe_version_id
     WHERE po.status = 'planned'
       AND NOT EXISTS (SELECT 1 FROM app.allocations a WHERE a.production_order_id = po.id AND a.released_at IS NULL)
     GROUP BY rl.item_id
)
SELECT i.code AS item_code, i.name AS item_name, i.item_class, i.base_unit_code,
       COALESCE(st.on_hand, 0) AS on_hand, COALESCE(st.released_available, 0) AS released_available,
       COALESCE(al.qty, 0) AS allocated_to_orders, COALESCE(rq.qty, 0) AS planned_requirements,
       COALESCE(st.released_available, 0) - COALESCE(al.qty, 0) - COALESCE(rq.qty, 0) AS net_available,
       vs.qty_on_order, al.orders AS allocated_orders, rq.orders AS planned_orders
  FROM app.items i
  LEFT JOIN stock st ON st.item_id = i.id
  LEFT JOIN alloc al ON al.item_id = i.id
  LEFT JOIN req rq ON rq.item_id = i.id
  LEFT JOIN app.v_item_stock vs ON vs.item_id = i.id
 WHERE i.active
   AND (CAST(%(item_id)s AS bigint) IS NULL OR i.id = %(item_id)s)
   AND (CAST(%(item_class)s AS text) IS NULL OR i.item_class = %(item_class)s)
   AND (CAST(%(item_id)s AS bigint) IS NOT NULL OR st.on_hand IS NOT NULL OR al.qty IS NOT NULL OR rq.qty IS NOT NULL)
 ORDER BY (COALESCE(al.qty, 0) + COALESCE(rq.qty, 0) > 0) DESC, net_available, i.code
 LIMIT %(lim)s OFFSET %(off)s
"""

PICK_ORDER = """
SELECT i.code AS item_code, i.name AS item_name, i.base_unit_code, i.item_class, l.lot_number, l.quality_status, l.expires_on,
       COALESCE(l.received_on, l.produced_on) AS received_on, loc.name AS location_name,
       b.qty_on_hand, (b.qty_on_hand - b.qty_allocated) AS qty_available,
       CASE WHEN l.expires_on IS NOT NULL THEN l.expires_on - (now() AT TIME ZONE %(tz)s)::date END AS days_to_expiry
  FROM app.inventory_balances b
  JOIN app.lots l ON l.id = b.lot_id
  JOIN app.items i ON i.id = b.item_id
  JOIN app.locations loc ON loc.id = b.location_id
 WHERE b.qty_on_hand > 0
   AND (CAST(%(item_id)s AS bigint) IS NULL OR b.item_id = %(item_id)s)
   AND (CAST(%(location_id)s AS bigint) IS NULL OR b.location_id = %(location_id)s)
   AND (CAST(%(within)s AS int) IS NULL OR (l.expires_on IS NOT NULL AND l.expires_on <= (now() AT TIME ZONE %(tz)s)::date + CAST(%(within)s AS int)))
 ORDER BY i.code, (l.quality_status = 'released') DESC, l.expires_on NULLS LAST, COALESCE(l.received_on, l.produced_on) NULLS LAST, l.id, loc.name
 LIMIT %(lim)s OFFSET %(off)s
"""

BELOW_REORDER = """
SELECT v.code AS item_code, v.name AS item_name, v.item_class, v.base_unit_code, v.qty_on_hand, v.qty_allocated, v.qty_available,
       v.qty_on_order, v.reorder_point_base, v.min_qty_base, v.max_qty_base,
       (v.reorder_point_base - v.qty_available) AS shortfall,
       GREATEST(v.reorder_point_base - v.qty_available - v.qty_on_order, 0) AS shortfall_after_on_order,
       CASE WHEN v.max_qty_base IS NOT NULL THEN GREATEST(v.max_qty_base - v.qty_available - v.qty_on_order, 0) END AS suggested_order_to_max
  FROM app.v_item_stock v
 WHERE v.below_reorder_point
   AND (CAST(%(item_class)s AS text) IS NULL OR v.item_class = %(item_class)s)
 ORDER BY (v.reorder_point_base - v.qty_available) / NULLIF(v.reorder_point_base, 0) DESC, v.code
 LIMIT %(lim)s OFFSET %(off)s
"""

REORDER_CONFIGURED = """
SELECT count(*) FILTER (WHERE reorder_point_base IS NOT NULL) AS items_with_reorder_point, count(*) AS active_items
  FROM app.v_item_stock WHERE (CAST(%(item_class)s AS text) IS NULL OR item_class = %(item_class)s)
"""

ADJUSTMENTS = """
SELECT a.number, a.status, a.adjusted_at, a.posted_at, loc.name AS location_name, rc.code AS reason_code, rc.name AS reason_name,
       rc.ttb_category, a.notes, uc.display_name AS created_by, ua.display_name AS approved_by, a.approved_at, up.display_name AS posted_by,
       (SELECT jsonb_agg(jsonb_build_object('item_code', i.code, 'item_name', i.name, 'item_class', i.item_class, 'unit', i.base_unit_code,
                         'lot_number', l.lot_number, 'qty_delta_base', al.qty_delta_base,
                         'value', round(al.qty_delta_base * COALESCE(al.unit_cost_base, l.unit_cost_base), 2), 'note', al.note) ORDER BY al.id)
          FROM app.inventory_adjustment_lines al JOIN app.items i ON i.id = al.item_id JOIN app.lots l ON l.id = al.lot_id
         WHERE al.adjustment_id = a.id) AS lines
  FROM app.inventory_adjustments a
  JOIN app.locations loc ON loc.id = a.location_id
  JOIN app.reason_codes rc ON rc.id = a.reason_code_id
  LEFT JOIN app.users uc ON uc.id = a.created_by
  LEFT JOIN app.users ua ON ua.id = a.approved_by
  LEFT JOIN app.users up ON up.id = a.posted_by
 WHERE (CAST(%(location_id)s AS bigint) IS NULL OR a.location_id = %(location_id)s)
   AND (CAST(%(reason_id)s AS bigint) IS NULL OR a.reason_code_id = %(reason_id)s)
   AND (CAST(%(status)s AS text) IS NULL OR a.status = %(status)s)
   AND (CAST(%(date_from)s AS date) IS NULL OR (a.adjusted_at AT TIME ZONE %(tz)s)::date >= %(date_from)s)
   AND (CAST(%(date_to)s AS date) IS NULL OR (a.adjusted_at AT TIME ZONE %(tz)s)::date <= %(date_to)s)
 ORDER BY a.adjusted_at DESC, a.id DESC
 LIMIT %(lim)s OFFSET %(off)s
"""

COUNTS_AT_LOCATION = """
SELECT c.id, c.number, c.kind, c.status, c.started_at, c.completed_at, c.approved_at, us.display_name AS started_by,
       ua.display_name AS approved_by, c.notes, loc.name AS location_name
  FROM app.inventory_counts c
  JOIN app.locations loc ON loc.id = c.location_id
  LEFT JOIN app.users us ON us.id = c.started_by
  LEFT JOIN app.users ua ON ua.id = c.approved_by
 WHERE c.location_id = %(location_id)s
 ORDER BY (c.status = 'approved') DESC, (c.status <> 'cancelled') DESC, COALESCE(c.approved_at, c.completed_at, c.started_at) DESC
 LIMIT 6
"""

COUNT_LINES = """
SELECT i.code AS item_code, i.name AS item_name, i.item_class, i.base_unit_code, l.lot_number, cl.qty_expected_base, cl.qty_counted_base,
       cl.variance_base, round(cl.variance_base * l.unit_cost_base, 2) AS variance_value, u.display_name AS counted_by, cl.counted_at, cl.note
  FROM app.inventory_count_lines cl
  JOIN app.items i ON i.id = cl.item_id
  JOIN app.lots l ON l.id = cl.lot_id
  LEFT JOIN app.users u ON u.id = cl.counted_by
 WHERE cl.count_id = %(count_id)s
 ORDER BY abs(COALESCE(cl.variance_base, 0)) DESC, i.code
"""

COUNT_CORRECTIONS = """
SELECT t.occurred_at, i.code AS item_code, i.base_unit_code, l.lot_number, t.qty_base, t.ttb_category, rc.code AS reason_code
  FROM app.inventory_transactions t
  JOIN app.items i ON i.id = t.item_id
  JOIN app.lots l ON l.id = t.lot_id
  LEFT JOIN app.reason_codes rc ON rc.id = t.reason_code_id
 WHERE t.reference_kind = 'count' AND t.reference_id = %(count_id)s
 ORDER BY t.id
"""

SLOW_MOVERS = """
WITH stock AS (
    SELECT b.item_id, sum(b.qty_on_hand) AS on_hand,
           sum(b.qty_on_hand * CASE WHEN i.costing_method = 'standard' THEN COALESCE(i.standard_cost_per_base, 0) ELSE l.unit_cost_base END) AS value
      FROM app.inventory_balances b JOIN app.lots l ON l.id = b.lot_id JOIN app.items i ON i.id = b.item_id
     WHERE b.qty_on_hand > 0 GROUP BY b.item_id
), moves AS (
    SELECT t.item_id, max(t.occurred_at) AS last_movement_at,
           max(t.occurred_at) FILTER (WHERE t.qty_base < 0 AND t.txn_type IN ('issue','packaging_output','removal','destruction','transfer_out')) AS last_outbound_at,
           min(t.occurred_at) FILTER (WHERE t.qty_base > 0) AS first_inbound_at
      FROM app.inventory_transactions t GROUP BY t.item_id
)
SELECT i.code AS item_code, i.name AS item_name, i.item_class, i.base_unit_code, s.on_hand, s.value,
       m.first_inbound_at, m.last_outbound_at, m.last_movement_at,
       (now()::date - COALESCE(m.last_outbound_at, m.first_inbound_at)::date) AS days_without_outbound
  FROM stock s
  JOIN app.items i ON i.id = s.item_id
  LEFT JOIN moves m ON m.item_id = s.item_id
 WHERE (CAST(%(item_class)s AS text) IS NULL OR i.item_class = %(item_class)s)
   AND m.first_inbound_at < now() - make_interval(months => CAST(%(months)s AS int))
   AND (m.last_outbound_at IS NULL OR m.last_outbound_at < now() - make_interval(months => CAST(%(months)s AS int)))
 ORDER BY s.value DESC NULLS LAST, i.code
 LIMIT %(lim)s OFFSET %(off)s
"""

MOVEMENTS = """
SELECT t.occurred_at, t.txn_type, i.code AS item_code, i.name AS item_name, i.item_class, i.base_unit_code, l.lot_number,
       loc.name AS location_name, t.tax_state, t.qty_base, round(t.qty_base * t.unit_cost_base, 2) AS value, t.ttb_category,
       t.reference_kind,
       CASE t.reference_kind
         WHEN 'goods_receipt' THEN (SELECT number FROM app.goods_receipts WHERE id = t.reference_id)
         WHEN 'transfer' THEN (SELECT number FROM app.inventory_transfers WHERE id = t.reference_id)
         WHEN 'adjustment' THEN (SELECT number FROM app.inventory_adjustments WHERE id = t.reference_id)
         WHEN 'count' THEN (SELECT number FROM app.inventory_counts WHERE id = t.reference_id)
         WHEN 'press_run' THEN (SELECT number FROM app.press_runs WHERE id = t.reference_id)
         WHEN 'packaging_run' THEN (SELECT number FROM app.packaging_runs WHERE id = t.reference_id)
         WHEN 'removal' THEN (SELECT number FROM app.removals WHERE id = t.reference_id)
         WHEN 'return' THEN (SELECT number FROM app.removals WHERE id = t.reference_id)
         WHEN 'batch_consumption' THEN (SELECT b.number FROM app.consumptions c JOIN app.batches b ON b.id = c.batch_id WHERE c.id = t.reference_id)
         WHEN 'batch_output' THEN (SELECT number FROM app.batches WHERE id = t.reference_id)
         ELSE NULL END AS reference_number,
       CASE WHEN t.counterparty_kind = 'batch' THEN (SELECT number FROM app.batches WHERE id = t.counterparty_id)
            WHEN t.counterparty_kind = 'customer' THEN (SELECT name FROM app.customers WHERE id = t.counterparty_id)
            WHEN t.counterparty_kind = 'supplier' THEN (SELECT name FROM app.suppliers WHERE id = t.counterparty_id)
            WHEN t.counterparty_kind = 'location' THEN (SELECT name FROM app.locations WHERE id = t.counterparty_id)
            ELSE NULL END AS counterparty,
       rc.code AS reason_code, u.display_name AS actor, t.note, fl.unit_volume_l
  FROM app.inventory_transactions t
  JOIN app.items i ON i.id = t.item_id
  JOIN app.lots l ON l.id = t.lot_id
  JOIN app.locations loc ON loc.id = t.location_id
  LEFT JOIN app.reason_codes rc ON rc.id = t.reason_code_id
  LEFT JOIN app.users u ON u.id = t.actor_id
  LEFT JOIN app.finished_lots fl ON fl.lot_id = t.lot_id
 WHERE (CAST(%(item_id)s AS bigint) IS NULL OR t.item_id = %(item_id)s)
   AND (CAST(%(lot_id)s AS bigint) IS NULL OR t.lot_id = %(lot_id)s)
   AND (CAST(%(location_id)s AS bigint) IS NULL OR t.location_id = %(location_id)s)
   AND (CAST(%(txn_type)s AS text) IS NULL OR t.txn_type = %(txn_type)s)
   AND (CAST(%(item_class)s AS text) IS NULL OR i.item_class = %(item_class)s)
   AND (CAST(%(date_from)s AS date) IS NULL OR (t.occurred_at AT TIME ZONE %(tz)s)::date >= %(date_from)s)
   AND (CAST(%(date_to)s AS date) IS NULL OR (t.occurred_at AT TIME ZONE %(tz)s)::date <= %(date_to)s)
 ORDER BY t.occurred_at DESC, t.id DESC
 LIMIT %(lim)s OFFSET %(off)s
"""

# ---------------------------------------------------------------------------- recipes and products

RECIPE_VERSION = """
SELECT rv.id, p.name AS product_name, p.code AS product_code, rv.version_no, rv.status, rv.target_batch_volume_l,
       rv.expected_total_loss_pct, rv.standard_cost_total, rv.standard_cost_per_l, rv.change_note, rv.activated_at,
       ua.display_name AS activated_by, rv.created_at
  FROM app.recipe_versions rv
  JOIN app.products p ON p.id = rv.product_id
  LEFT JOIN app.users ua ON ua.id = rv.activated_by
 WHERE rv.product_id = %(product_id)s
   AND ((CAST(%(version_no)s AS int) IS NULL AND rv.status = 'active') OR rv.version_no = %(version_no)s)
"""

RECIPE_VERSIONS_ALL = """
SELECT rv.id, rv.version_no, rv.status, rv.activated_at FROM app.recipe_versions rv WHERE rv.product_id = %(product_id)s ORDER BY rv.version_no
"""

RECIPE_STAGES = """
SELECT rs.seq, rs.stage_code, st.name AS stage_name, rs.expected_loss_pct, rs.expected_duration_days, rs.instructions
  FROM app.recipe_stages rs JOIN app.stages st ON st.code = rs.stage_code
 WHERE rs.recipe_version_id = %(rv_id)s ORDER BY rs.seq
"""

RECIPE_LINES = """
SELECT rl.seq, rl.item_id, i.code AS item_code, i.name AS item_name, i.item_class, i.base_unit_code, rl.stage_code, rl.purpose,
       rl.qty_per_batch_base, rl.qty_per_l, rl.consumption_mode, rl.notes,
       COALESCE(rl.qty_per_batch_base, rl.qty_per_l * CAST(%(volume_l)s AS numeric)) AS qty_required,
       COALESCE((SELECT sc.cost_per_base FROM app.standard_costs sc WHERE sc.item_id = i.id AND sc.effective_from <= (now() AT TIME ZONE %(tz)s)::date
                 ORDER BY sc.effective_from DESC LIMIT 1), i.standard_cost_per_base) AS standard_cost_per_base,
       (SELECT l.unit_cost_base FROM app.lots l WHERE l.item_id = i.id AND l.unit_cost_base > 0
         ORDER BY COALESCE(l.received_on, l.produced_on) DESC NULLS LAST, l.id DESC LIMIT 1) AS last_lot_cost_per_base,
       COALESCE((SELECT sum(b.qty_on_hand - b.qty_allocated) FROM app.inventory_balances b JOIN app.lots l ON l.id = b.lot_id
                  WHERE b.item_id = i.id AND l.quality_status = 'released'), 0) AS released_available,
       COALESCE((SELECT sum(b.qty_on_hand) FROM app.inventory_balances b WHERE b.item_id = i.id), 0) AS on_hand_all_statuses,
       COALESCE((SELECT sum(a.qty_base) FROM app.allocations a JOIN app.production_orders po ON po.id = a.production_order_id
                  WHERE a.item_id = i.id AND a.released_at IS NULL AND a.lot_id IS NULL AND po.status IN ('planned','released','in_progress')), 0) AS allocated_to_orders,
       vs.qty_on_order
  FROM app.recipe_lines rl
  JOIN app.items i ON i.id = rl.item_id
  LEFT JOIN app.v_item_stock vs ON vs.item_id = i.id
 WHERE rl.recipe_version_id = %(rv_id)s
 ORDER BY rl.seq
"""

OVERHEAD_RATE = """
SELECT r.rate_per_l, r.effective_from, p.name AS premises_name
  FROM app.overhead_rates r JOIN app.premises p ON p.id = r.premises_id
 WHERE (CAST(%(premises_id)s AS bigint) IS NULL OR r.premises_id = %(premises_id)s)
   AND r.effective_from <= (now() AT TIME ZONE %(tz)s)::date
 ORDER BY r.effective_from DESC LIMIT 1
"""

RECIPE_BATCHES = """
SELECT rv.version_no, rv.status AS version_status, b.number AS batch_number, b.status, b.current_stage_code, b.origin_kind,
       b.started_at, b.closed_at, b.current_volume_l, po.number AS production_order
  FROM app.batches b
  JOIN app.recipe_versions rv ON rv.id = b.recipe_version_id
  LEFT JOIN app.production_orders po ON po.id = b.production_order_id
 WHERE rv.product_id = %(product_id)s AND (CAST(%(version_no)s AS int) IS NULL OR rv.version_no = %(version_no)s)
 ORDER BY rv.version_no, b.started_at, b.number
 LIMIT %(lim)s OFFSET %(off)s
"""

RECIPE_BATCHES_NO_VERSION = """
SELECT count(*) AS batches, jsonb_agg(b.number ORDER BY b.number) AS batch_numbers
  FROM app.batches b WHERE b.product_id = %(product_id)s AND b.recipe_version_id IS NULL
"""

PRODUCT_APPROVALS = """
SELECT p.code AS product_code, p.name AS product_name, p.status AS product_status, pa.kind, pc.name AS package_name, pa.reference_no,
       pa.status, pa.approved_on, pa.expires_on,
       CASE WHEN pa.expires_on IS NOT NULL THEN pa.expires_on - (now() AT TIME ZONE %(tz)s)::date END AS days_to_expiry,
       pa.notes, (pa.attachment_id IS NOT NULL) AS has_document
  FROM app.product_approvals pa
  JOIN app.products p ON p.id = pa.product_id
  LEFT JOIN app.packaging_configurations pc ON pc.id = pa.packaging_configuration_id
 WHERE (CAST(%(product_id)s AS bigint) IS NULL OR pa.product_id = %(product_id)s)
   AND ((CAST(%(status)s AS text) IS NOT NULL AND pa.status = %(status)s)
        OR (CAST(%(status)s AS text) IS NULL AND (pa.status IN ('required','submitted','expired','rejected')
            OR (pa.status = 'approved' AND pa.expires_on IS NOT NULL AND pa.expires_on <= (now() AT TIME ZONE %(tz)s)::date + CAST(%(expiring_within_days)s AS int)))))
 ORDER BY CASE pa.status WHEN 'expired' THEN 0 WHEN 'rejected' THEN 1 WHEN 'required' THEN 2 WHEN 'submitted' THEN 3 ELSE 4 END,
          pa.expires_on NULLS LAST, p.name
 LIMIT %(lim)s OFFSET %(off)s
"""

PRODUCTS_WITHOUT_FORMULA = """
SELECT p.code AS product_code, p.name AS product_name, p.status
  FROM app.products p
 WHERE p.status = 'active' AND NOT EXISTS (SELECT 1 FROM app.product_approvals pa WHERE pa.product_id = p.id AND pa.kind = 'formula')
"""

PRODUCT_FIND = """
SELECT p.code, p.name, p.beverage_type, p.style, p.status, p.intended_tax_class, p.target_abv, p.target_fruit_share_pct,
       p.contains_other_fruit, p.contains_flavoring,
       (SELECT rv.version_no FROM app.recipe_versions rv WHERE rv.product_id = p.id AND rv.status = 'active') AS active_recipe_version,
       (SELECT count(*) FROM app.recipe_versions rv WHERE rv.product_id = p.id) AS recipe_versions,
       (SELECT count(*) FROM app.batches b WHERE b.product_id = p.id) AS batches,
       (SELECT count(*) FROM app.batches b WHERE b.product_id = p.id AND b.status = 'active') AS active_batches,
       (SELECT jsonb_agg(jsonb_build_object('name', pc.name, 'package_kind', pc.package_kind, 'fill_volume_l', pc.fill_volume_l, 'units_per_case', pc.units_per_case) ORDER BY pc.name)
          FROM app.packaging_configurations pc WHERE pc.product_id = p.id AND pc.active) AS packages
  FROM app.products p
 WHERE (CAST(%(pat)s AS text) IS NULL OR p.code ILIKE %(pat)s OR p.name ILIKE %(pat)s OR p.style ILIKE %(pat)s OR similarity(p.name, %(q)s) > 0.3)
   AND (CAST(%(status)s AS text) IS NULL OR p.status = %(status)s)
 ORDER BY (p.status = 'active') DESC, p.name
 LIMIT %(lim)s OFFSET %(off)s
"""

# ---------------------------------------------------------------------------- production

TANK_BOARD = """
SELECT vb.vessel_name, vb.vessel_kind, vb.capacity_l, vb.vessel_status, pr.name AS premises_name, vb.occupant_kind, vb.occupant_label,
       vb.volume_l, vb.fill_pct, vb.occupied_since, vb.current_stage_code, st.name AS stage_name, vb.product_name,
       b.status AS batch_status, se.entered_at AS stage_since,
       CASE WHEN vb.occupant_kind = 'lot' THEN (SELECT i.name FROM app.lots l JOIN app.items i ON i.id = l.item_id WHERE l.id = vb.occupant_id) END AS lot_item,
       vb.board_x, vb.board_y
  FROM app.v_vessel_board vb
  JOIN app.premises pr ON pr.id = vb.premises_id
  LEFT JOIN app.batches b ON vb.occupant_kind = 'batch' AND b.id = vb.occupant_id
  LEFT JOIN app.stages st ON st.code = vb.current_stage_code
  LEFT JOIN LATERAL (SELECT max(s.entered_at) AS entered_at FROM app.stage_events s WHERE s.batch_id = b.id AND s.stage_code = b.current_stage_code) se ON true
 WHERE (CAST(%(premises_id)s AS bigint) IS NULL OR vb.premises_id = %(premises_id)s)
   AND (CAST(%(vessel_id)s AS bigint) IS NULL OR vb.vessel_id = %(vessel_id)s)
   AND (%(include_empty)s OR vb.occupant_kind IS NOT NULL)
 ORDER BY vb.vessel_name
"""

BATCHES_IN_PROGRESS = """
SELECT b.number AS batch_number, p.name AS product_name, b.status, b.current_stage_code, st.name AS stage_name, b.origin_kind,
       b.current_volume_l, b.started_at, se.entered_at AS stage_since,
       (now()::date - COALESCE(se.entered_at, b.started_at)::date) AS days_in_stage,
       rv.version_no AS recipe_version, po.number AS production_order, po.planned_package_on,
       (SELECT jsonb_agg(v.name ORDER BY v.name) FROM app.vessel_occupancies o JOIN app.vessels v ON v.id = o.vessel_id
         WHERE o.occupant_kind = 'batch' AND o.occupant_id = b.id AND o.to_at IS NULL) AS vessels,
       (SELECT sum(rs.expected_duration_days) FROM app.recipe_stages rs JOIN app.stages s2 ON s2.code = rs.stage_code
         WHERE rs.recipe_version_id = b.recipe_version_id AND NOT s2.is_terminal AND s2.display_order >= st.display_order) AS remaining_stage_days,
       (SELECT jsonb_build_object('measurement', r.measurement_type_code, 'value', r.value, 'taken_at', r.taken_at, 'spec_result', r.spec_result)
          FROM app.readings r WHERE r.target_kind = 'batch' AND r.target_id = b.id ORDER BY r.taken_at DESC, r.id DESC LIMIT 1) AS last_reading
  FROM app.batches b
  JOIN app.products p ON p.id = b.product_id
  JOIN app.stages st ON st.code = b.current_stage_code
  LEFT JOIN app.recipe_versions rv ON rv.id = b.recipe_version_id
  LEFT JOIN app.production_orders po ON po.id = b.production_order_id
  LEFT JOIN LATERAL (SELECT max(s.entered_at) AS entered_at FROM app.stage_events s WHERE s.batch_id = b.id AND s.stage_code = b.current_stage_code) se ON true
 WHERE (b.status = 'active' OR %(include_finished)s)
   AND (CAST(%(product_id)s AS bigint) IS NULL OR b.product_id = %(product_id)s)
   AND (CAST(%(stage)s AS text) IS NULL OR b.current_stage_code = %(stage)s)
 ORDER BY st.display_order DESC, b.number
 LIMIT %(lim)s OFFSET %(off)s
"""

PRESS_RUNS = """
SELECT pr.number, pr.run_on, pr.status, v.name AS press, pr.fruit_kg_total, pr.juice_l_total, pr.pomace_kg_total, pr.yield_l_per_kg,
       CASE WHEN pr.fruit_kg_total > 0 THEN (pr.juice_l_total / 3.785411784) / (pr.fruit_kg_total / 907.18474) END AS gal_per_ton,
       CASE WHEN pr.fruit_kg_total > 0 THEN (pr.juice_l_total / 3.785411784) / (pr.fruit_kg_total / 19.05087954) END AS gal_per_bushel,
       (SELECT jsonb_agg(jsonb_build_object('lot_number', l.lot_number, 'item', i.name, 'variety', va.value_text, 'kg', pi.qty_kg) ORDER BY pi.id)
          FROM app.press_run_inputs pi JOIN app.lots l ON l.id = pi.lot_id JOIN app.items i ON i.id = l.item_id
          LEFT JOIN app.lot_attributes va ON va.lot_id = l.id AND va.key = 'variety' WHERE pi.press_run_id = pr.id) AS inputs,
       (SELECT jsonb_agg(jsonb_build_object('kind', o.kind, 'lot_number', l.lot_number, 'item', i.name, 'qty_base', o.qty_base, 'unit', i.base_unit_code,
                         'brix', o.brix, 'vessel', ov.name, 'location', ol.name) ORDER BY o.id)
          FROM app.press_run_outputs o JOIN app.items i ON i.id = o.item_id LEFT JOIN app.lots l ON l.id = o.lot_id
          LEFT JOIN app.vessels ov ON ov.id = o.vessel_id LEFT JOIN app.locations ol ON ol.id = o.location_id WHERE o.press_run_id = pr.id) AS outputs,
       pr.notes, up.display_name AS posted_by
  FROM app.press_runs pr
  LEFT JOIN app.vessels v ON v.id = pr.press_vessel_id
  LEFT JOIN app.users up ON up.id = pr.posted_by
 WHERE (CAST(%(status)s AS text) IS NULL OR pr.status = %(status)s)
   AND (CAST(%(date_from)s AS date) IS NULL OR pr.run_on >= %(date_from)s)
   AND (CAST(%(date_to)s AS date) IS NULL OR pr.run_on <= %(date_to)s)
 ORDER BY pr.run_on DESC, pr.number DESC
 LIMIT %(lim)s OFFSET %(off)s
"""

PRODUCTION_ORDERS = """
SELECT po.id, po.number, po.status, p.name AS product_name, rv.version_no AS recipe_version, po.planned_volume_l, po.planned_pitch_on,
       po.planned_package_on, po.released_at, ur.display_name AS released_by, po.created_at, po.notes,
       (SELECT jsonb_agg(jsonb_build_object('vessel', v.name, 'role', pov.role, 'from', pov.planned_from, 'to', pov.planned_to) ORDER BY pov.planned_from)
          FROM app.production_order_vessels pov JOIN app.vessels v ON v.id = pov.vessel_id WHERE pov.production_order_id = po.id) AS vessels,
       (SELECT jsonb_agg(jsonb_build_object('resource', s.resource_name, 'resource_kind', s.resource_kind, 'role', s.role, 'from', s.local_from, 'to', s.local_to,
                         'all_day', s.all_day, 'shared', s.shared, 'overlaps', s.clash_count) ORDER BY s.starts_at)
          FROM app.v_equipment_schedule s WHERE s.subject_kind = 'production_order' AND s.subject_id = po.id) AS equipment,
       (SELECT jsonb_agg(jsonb_build_object('item_code', i.code, 'qty_base', a.qty_base, 'unit', i.base_unit_code, 'lot', l.lot_number,
                         'open', a.released_at IS NULL) ORDER BY i.code)
          FROM app.allocations a JOIN app.items i ON i.id = a.item_id LEFT JOIN app.lots l ON l.id = a.lot_id WHERE a.production_order_id = po.id) AS allocations,
       (SELECT jsonb_agg(jsonb_build_object('batch', b.number, 'status', b.status, 'stage', b.current_stage_code) ORDER BY b.number)
          FROM app.batches b WHERE b.production_order_id = po.id) AS batches
  FROM app.production_orders po
  JOIN app.products p ON p.id = po.product_id
  JOIN app.recipe_versions rv ON rv.id = po.recipe_version_id
  LEFT JOIN app.users ur ON ur.id = po.released_by
 WHERE (CAST(%(statuses)s AS text[]) IS NULL OR po.status = ANY(%(statuses)s))
   AND (CAST(%(product_id)s AS bigint) IS NULL OR po.product_id = %(product_id)s)
   AND (NOT %(not_started_only)s OR NOT EXISTS (SELECT 1 FROM app.batches b WHERE b.production_order_id = po.id))
 ORDER BY po.planned_pitch_on NULLS LAST, po.number
 LIMIT %(lim)s OFFSET %(off)s
"""

ORDER_REQUIREMENTS = """
SELECT po.id AS order_id, po.number, po.status, po.planned_pitch_on, po.planned_volume_l, p.name AS product_name,
       rl.item_id, i.code AS item_code, i.name AS item_name, i.item_class, i.base_unit_code, rl.stage_code, rl.purpose,
       COALESCE(rl.qty_per_batch_base, rl.qty_per_l * po.planned_volume_l) AS required,
       COALESCE((SELECT sum(c.qty_base) FROM app.consumptions c JOIN app.batches b ON b.id = c.batch_id
                  WHERE b.production_order_id = po.id AND c.item_id = rl.item_id), 0) AS consumed
  FROM app.production_orders po
  JOIN app.products p ON p.id = po.product_id
  JOIN app.recipe_lines rl ON rl.recipe_version_id = po.recipe_version_id
  JOIN app.items i ON i.id = rl.item_id
 WHERE ((CAST(%(order_id)s AS bigint) IS NULL AND po.status IN ('planned','released','in_progress')) OR po.id = %(order_id)s)
 ORDER BY po.planned_pitch_on NULLS LAST, po.number, rl.seq
"""

ITEM_SUPPLY = """
SELECT i.id AS item_id,
       COALESCE((SELECT sum(b.qty_on_hand - b.qty_allocated) FROM app.inventory_balances b JOIN app.lots l ON l.id = b.lot_id
                  WHERE b.item_id = i.id AND l.quality_status = 'released'), 0) AS released_available,
       COALESCE((SELECT sum(b.qty_on_hand) FROM app.inventory_balances b JOIN app.lots l ON l.id = b.lot_id
                  WHERE b.item_id = i.id AND l.quality_status IN ('quarantine','hold')), 0) AS awaiting_release,
       COALESCE(vs.qty_on_order, 0) AS on_order,
       (SELECT min(v.expected_on) FROM app.v_open_po_lines v WHERE v.item_id = i.id) AS next_expected_on
  FROM app.items i LEFT JOIN app.v_item_stock vs ON vs.item_id = i.id
 WHERE i.id = ANY(%(item_ids)s)
"""

BATCH_FIND = """
SELECT b.number AS batch_number, p.name AS product_name, b.status, b.current_stage_code, b.origin_kind, b.current_volume_l, b.started_at, b.closed_at,
       rv.version_no AS recipe_version, po.number AS production_order,
       (SELECT jsonb_agg(v.name ORDER BY v.name) FROM app.vessel_occupancies o JOIN app.vessels v ON v.id = o.vessel_id
         WHERE o.occupant_kind = 'batch' AND o.occupant_id = b.id AND o.to_at IS NULL) AS vessels
  FROM app.batches b
  JOIN app.products p ON p.id = b.product_id
  LEFT JOIN app.recipe_versions rv ON rv.id = b.recipe_version_id
  LEFT JOIN app.production_orders po ON po.id = b.production_order_id
 WHERE (CAST(%(pat)s AS text) IS NULL OR b.number ILIKE %(pat)s OR p.name ILIKE %(pat)s OR p.code ILIKE %(pat)s OR b.notes ILIKE %(pat)s)
   AND (CAST(%(product_id)s AS bigint) IS NULL OR b.product_id = %(product_id)s)
   AND (CAST(%(status)s AS text) IS NULL OR b.status = %(status)s)
   AND (CAST(%(date_from)s AS date) IS NULL OR (b.started_at AT TIME ZONE %(tz)s)::date >= %(date_from)s)
   AND (CAST(%(date_to)s AS date) IS NULL OR (b.started_at AT TIME ZONE %(tz)s)::date <= %(date_to)s)
 ORDER BY b.started_at DESC, b.number DESC
 LIMIT %(lim)s OFFSET %(off)s
"""

BATCH_HEADER = """
SELECT b.id, b.number, p.name AS product_name, p.id AS product_id, b.status, b.current_stage_code, b.origin_kind, b.current_volume_l, b.started_at,
       b.closed_at, rv.version_no AS recipe_version, b.recipe_version_id, b.fruit_share_pct, b.tax_class_derived, b.tax_class_override,
       p.intended_tax_class, p.contains_other_fruit, p.contains_flavoring, p.beverage_type
  FROM app.batches b JOIN app.products p ON p.id = b.product_id LEFT JOIN app.recipe_versions rv ON rv.id = b.recipe_version_id
 WHERE b.id = %(batch_id)s
"""

BATCH_CONSUMPTIONS = """
SELECT c.consumed_at, i.code AS item_code, i.name AS item_name, i.item_class, i.base_unit_code, l.lot_number, l.supplier_lot_number,
       s.name AS supplier_name, c.qty_base, c.planned_qty_base, c.purpose, c.stage_code, st.name AS stage_name, u.display_name AS actor, c.note,
       round(c.qty_base * l.unit_cost_base, 2) AS cost
  FROM app.consumptions c
  JOIN app.items i ON i.id = c.item_id
  JOIN app.lots l ON l.id = c.lot_id
  LEFT JOIN app.suppliers s ON s.id = l.supplier_id
  LEFT JOIN app.stages st ON st.code = c.stage_code
  LEFT JOIN app.users u ON u.id = c.actor_id
 WHERE c.batch_id = %(batch_id)s
   AND (CAST(%(purpose)s AS text) IS NULL OR c.purpose = %(purpose)s)
   AND (CAST(%(stage)s AS text) IS NULL OR c.stage_code = %(stage)s)
   AND (CAST(%(after_stage)s AS text) IS NULL
        OR st.display_order > (SELECT display_order FROM app.stages WHERE code = %(after_stage)s))
 ORDER BY c.consumed_at, c.id
"""

BATCH_BLENDS_AS_RESULT = """
SELECT bb.id, bb.blended_at, v.name AS vessel, bb.volume_out_l, u.display_name AS actor, bb.note,
       (SELECT jsonb_agg(jsonb_build_object('source_batch', sb.number, 'product', sp.name, 'volume_l', bi.volume_l,
                         'fraction_pct', round(100 * bi.volume_l / NULLIF((SELECT sum(x.volume_l) FROM app.batch_blend_inputs x WHERE x.blend_id = bb.id), 0), 2)) ORDER BY bi.volume_l DESC)
          FROM app.batch_blend_inputs bi JOIN app.batches sb ON sb.id = bi.source_batch_id JOIN app.products sp ON sp.id = sb.product_id
         WHERE bi.blend_id = bb.id) AS inputs
  FROM app.batch_blends bb JOIN app.vessels v ON v.id = bb.vessel_id LEFT JOIN app.users u ON u.id = bb.actor_id
 WHERE bb.result_batch_id = %(batch_id)s
 ORDER BY bb.blended_at
"""

BATCH_BLENDS_AS_SOURCE = """
SELECT bb.blended_at, rb.number AS result_batch, bi.volume_l AS volume_contributed_l, bb.volume_out_l,
       round(100 * bi.volume_l / NULLIF((SELECT sum(x.volume_l) FROM app.batch_blend_inputs x WHERE x.blend_id = bb.id), 0), 2) AS share_of_blend_pct
  FROM app.batch_blend_inputs bi JOIN app.batch_blends bb ON bb.id = bi.blend_id JOIN app.batches rb ON rb.id = bb.result_batch_id
 WHERE bi.source_batch_id = %(batch_id)s
 ORDER BY bb.blended_at
"""

RECENT_BLENDS = """
SELECT rb.number AS result_batch, rp.name AS product_name, bb.blended_at, v.name AS vessel, bb.volume_out_l,
       (SELECT jsonb_agg(jsonb_build_object('source_batch', sb.number, 'volume_l', bi.volume_l,
                         'fraction_pct', round(100 * bi.volume_l / NULLIF((SELECT sum(x.volume_l) FROM app.batch_blend_inputs x WHERE x.blend_id = bb.id), 0), 2)) ORDER BY bi.volume_l DESC)
          FROM app.batch_blend_inputs bi JOIN app.batches sb ON sb.id = bi.source_batch_id WHERE bi.blend_id = bb.id) AS inputs
  FROM app.batch_blends bb JOIN app.batches rb ON rb.id = bb.result_batch_id JOIN app.products rp ON rp.id = rb.product_id
  JOIN app.vessels v ON v.id = bb.vessel_id
 WHERE (CAST(%(date_from)s AS date) IS NULL OR (bb.blended_at AT TIME ZONE %(tz)s)::date >= %(date_from)s)
   AND (CAST(%(date_to)s AS date) IS NULL OR (bb.blended_at AT TIME ZONE %(tz)s)::date <= %(date_to)s)
 ORDER BY bb.blended_at DESC
 LIMIT %(lim)s OFFSET %(off)s
"""

LINEAGE_UP = """
WITH RECURSIVE up AS (
    SELECT bl.child_batch_id, bl.parent_batch_id, bl.event_kind, bl.volume_l, bl.fraction, 1 AS depth, ARRAY[bl.child_batch_id] AS path
      FROM app.batch_lineage bl WHERE bl.child_batch_id = %(batch_id)s
    UNION ALL
    SELECT bl.child_batch_id, bl.parent_batch_id, bl.event_kind, bl.volume_l, bl.fraction, up.depth + 1, up.path || bl.child_batch_id
      FROM app.batch_lineage bl JOIN up ON bl.child_batch_id = up.parent_batch_id
     WHERE NOT bl.child_batch_id = ANY(up.path) AND up.depth < 25
)
SELECT up.depth, c.number AS child_batch, pb.number AS parent_batch, pp.name AS parent_product, pb.status AS parent_status,
       up.event_kind, up.volume_l, round(100 * up.fraction, 2) AS fraction_pct
  FROM up JOIN app.batches c ON c.id = up.child_batch_id JOIN app.batches pb ON pb.id = up.parent_batch_id JOIN app.products pp ON pp.id = pb.product_id
 ORDER BY up.depth, pb.number
"""

LINEAGE_DOWN = """
WITH RECURSIVE down AS (
    SELECT bl.child_batch_id, bl.parent_batch_id, bl.event_kind, bl.volume_l, bl.fraction, 1 AS depth, ARRAY[bl.parent_batch_id] AS path
      FROM app.batch_lineage bl WHERE bl.parent_batch_id = %(batch_id)s
    UNION ALL
    SELECT bl.child_batch_id, bl.parent_batch_id, bl.event_kind, bl.volume_l, bl.fraction, down.depth + 1, down.path || bl.parent_batch_id
      FROM app.batch_lineage bl JOIN down ON bl.parent_batch_id = down.child_batch_id
     WHERE NOT bl.parent_batch_id = ANY(down.path) AND down.depth < 25
)
SELECT down.depth, pb.number AS parent_batch, c.number AS child_batch, c.status AS child_status, down.event_kind, down.volume_l,
       round(100 * down.fraction, 2) AS fraction_pct
  FROM down JOIN app.batches c ON c.id = down.child_batch_id JOIN app.batches pb ON pb.id = down.parent_batch_id
 ORDER BY down.depth, c.number
"""

TRACE_BACKWARD = """
SELECT t.level, t.kind, t.label, t.detail, s.name AS supplier_name, l.supplier_lot_number, l.quality_status
  FROM app.trace_backward(%(batch_id)s) t
  LEFT JOIN app.lots l ON t.kind IN ('lot','fruit_lot') AND l.id = t.id
  LEFT JOIN app.suppliers s ON s.id = l.supplier_id
 ORDER BY t.level, t.kind, t.label
"""

VESSEL_OCCUPANT = """
SELECT o.occupant_kind, o.occupant_id, o.volume_l, o.from_at, v.name AS vessel_name
  FROM app.vessel_occupancies o JOIN app.vessels v ON v.id = o.vessel_id
 WHERE o.vessel_id = %(vessel_id)s AND o.to_at IS NULL
"""

LOT_ORIGIN = """
SELECT l.lot_number, i.name AS item_name, l.source_kind, pr.number AS press_run,
       (SELECT jsonb_agg(jsonb_build_object('fruit_lot', fl.lot_number, 'item', fi.name, 'supplier', s.name, 'kg', pi.qty_kg) ORDER BY pi.id)
          FROM app.press_run_inputs pi JOIN app.lots fl ON fl.id = pi.lot_id JOIN app.items fi ON fi.id = fl.item_id
          LEFT JOIN app.suppliers s ON s.id = fl.supplier_id WHERE pi.press_run_id = pr.id) AS fruit_lots
  FROM app.lots l JOIN app.items i ON i.id = l.item_id
  LEFT JOIN app.press_run_outputs o ON o.lot_id = l.id
  LEFT JOIN app.press_runs pr ON pr.id = o.press_run_id
 WHERE l.id = %(lot_id)s
"""

READINGS = """
SELECT r.taken_at, r.measurement_type_code AS measurement, mt.name AS measurement_name, mt.unit, r.value, r.stage_code, r.method, r.is_lab,
       r.spec_result, sp.min_value AS spec_min, sp.max_value AS spec_max, sp.target_value AS spec_target, u.display_name AS analyst, r.note
  FROM app.readings r
  JOIN app.measurement_types mt ON mt.code = r.measurement_type_code
  LEFT JOIN app.specs sp ON sp.id = r.spec_id
  LEFT JOIN app.users u ON u.id = r.analyst_id
 WHERE r.target_kind = %(target_kind)s AND r.target_id = ANY(%(target_ids)s)
   AND (CAST(%(measurements)s AS text[]) IS NULL OR r.measurement_type_code = ANY(%(measurements)s))
   AND (CAST(%(date_from)s AS date) IS NULL OR (r.taken_at AT TIME ZONE %(tz)s)::date >= %(date_from)s)
   AND (CAST(%(date_to)s AS date) IS NULL OR (r.taken_at AT TIME ZONE %(tz)s)::date <= %(date_to)s)
 ORDER BY r.taken_at, r.id
 LIMIT %(lim)s OFFSET %(off)s
"""

LATEST_READINGS = """
SELECT DISTINCT ON (r.measurement_type_code) r.measurement_type_code AS measurement, mt.unit, r.value, r.taken_at, r.stage_code, r.spec_result
  FROM app.readings r JOIN app.measurement_types mt ON mt.code = r.measurement_type_code
 WHERE r.target_kind = %(target_kind)s AND r.target_id = ANY(%(target_ids)s)
   AND (CAST(%(measurements)s AS text[]) IS NULL OR r.measurement_type_code = ANY(%(measurements)s))
 ORDER BY r.measurement_type_code, r.taken_at DESC, r.id DESC
"""

BATCH_LOSS_EVENTS = """
SELECT le.occurred_at, le.stage_code, le.qty_base, le.unit_code, rc.code AS reason_code, rc.name AS reason_name, le.ttb_category,
       le.classification, le.reportable, ua.display_name AS approved_by, le.approved_at, u.display_name AS actor, le.note
  FROM app.loss_events le
  JOIN app.reason_codes rc ON rc.id = le.reason_code_id
  LEFT JOIN app.users ua ON ua.id = le.approved_by
  LEFT JOIN app.users u ON u.id = le.actor_id
 WHERE le.target_kind = 'batch' AND le.target_id = %(batch_id)s
   AND (CAST(%(stage)s AS text) IS NULL OR le.stage_code = %(stage)s)
 ORDER BY le.occurred_at, le.id
"""

BATCH_TRANSFERS = """
SELECT bt.transferred_at, fv.name AS from_vessel, tv.name AS to_vessel, bt.volume_l, bt.loss_l, u.display_name AS actor, bt.note
  FROM app.batch_transfers bt JOIN app.vessels fv ON fv.id = bt.from_vessel_id JOIN app.vessels tv ON tv.id = bt.to_vessel_id
  LEFT JOIN app.users u ON u.id = bt.actor_id
 WHERE bt.batch_id = %(batch_id)s ORDER BY bt.transferred_at
"""

BATCH_PACKAGING_LOSSES = """
SELECT pr.number AS run_number, pr.run_on, pc.name AS package_name, pr.volume_in_l, pr.volume_out_l, pr.loss_l, pc.expected_loss_pct
  FROM app.packaging_runs pr JOIN app.packaging_configurations pc ON pc.id = pr.packaging_configuration_id
 WHERE pr.batch_id = %(batch_id)s AND pr.status = 'posted' ORDER BY pr.run_on, pr.id
"""

STAGE_HISTORY = """
SELECT se.stage_code, st.name AS stage_name, se.entered_at, se.left_at,
       round(extract(epoch FROM (COALESCE(se.left_at, now()) - se.entered_at)) / 86400.0, 1) AS days_in_stage,
       se.volume_in_l, se.volume_out_l,
       CASE WHEN se.volume_in_l > 0 AND se.volume_out_l IS NOT NULL THEN round(100 * (se.volume_in_l - se.volume_out_l) / se.volume_in_l, 2) END AS actual_loss_pct,
       rs.expected_loss_pct, rs.expected_duration_days, u.display_name AS actor, se.note
  FROM app.stage_events se
  JOIN app.stages st ON st.code = se.stage_code
  JOIN app.batches b ON b.id = se.batch_id
  LEFT JOIN app.recipe_stages rs ON rs.recipe_version_id = b.recipe_version_id AND rs.stage_code = se.stage_code
  LEFT JOIN app.users u ON u.id = se.actor_id
 WHERE se.batch_id = %(batch_id)s
 ORDER BY se.entered_at, se.id
"""

CO_PRODUCT = """
SELECT d.disposed_at, l.lot_number, i.code AS item_code, i.name AS item_name, i.base_unit_code, d.qty_base, d.destination, d.recipient,
       u.display_name AS actor, d.note, pr.number AS from_press_run
  FROM app.co_product_dispositions d
  JOIN app.lots l ON l.id = d.lot_id
  JOIN app.items i ON i.id = l.item_id
  LEFT JOIN app.users u ON u.id = d.actor_id
  LEFT JOIN app.press_run_outputs o ON o.lot_id = l.id
  LEFT JOIN app.press_runs pr ON pr.id = o.press_run_id
 WHERE (CAST(%(destination)s AS text) IS NULL OR d.destination = %(destination)s)
   AND (CAST(%(lot_id)s AS bigint) IS NULL OR d.lot_id = %(lot_id)s)
   AND (CAST(%(date_from)s AS date) IS NULL OR (d.disposed_at AT TIME ZONE %(tz)s)::date >= %(date_from)s)
   AND (CAST(%(date_to)s AS date) IS NULL OR (d.disposed_at AT TIME ZONE %(tz)s)::date <= %(date_to)s)
 ORDER BY d.disposed_at DESC, d.id DESC
 LIMIT %(lim)s OFFSET %(off)s
"""

CO_PRODUCT_TOTALS = """
SELECT d.destination, i.base_unit_code, count(*) AS dispositions, sum(d.qty_base) AS qty_base
  FROM app.co_product_dispositions d JOIN app.lots l ON l.id = d.lot_id JOIN app.items i ON i.id = l.item_id
 WHERE (CAST(%(destination)s AS text) IS NULL OR d.destination = %(destination)s)
   AND (CAST(%(lot_id)s AS bigint) IS NULL OR d.lot_id = %(lot_id)s)
   AND (CAST(%(date_from)s AS date) IS NULL OR (d.disposed_at AT TIME ZONE %(tz)s)::date >= %(date_from)s)
   AND (CAST(%(date_to)s AS date) IS NULL OR (d.disposed_at AT TIME ZONE %(tz)s)::date <= %(date_to)s)
 GROUP BY d.destination, i.base_unit_code ORDER BY sum(d.qty_base) DESC
"""

CO_PRODUCT_ON_HAND = """
SELECT l.lot_number, i.name AS item_name, i.base_unit_code, loc.name AS location_name, b.qty_on_hand
  FROM app.inventory_balances b JOIN app.items i ON i.id = b.item_id AND i.item_class = 'co_product'
  JOIN app.lots l ON l.id = b.lot_id JOIN app.locations loc ON loc.id = b.location_id
 WHERE b.qty_on_hand > 0 ORDER BY l.lot_number
"""

# ---------------------------------------------------------------------------- packaging and kegs

PACKAGING_RUNS = """
SELECT pr.number AS run_number, pr.run_on, pr.status, b.number AS batch_number, p.name AS product_name, pc.name AS package_name, pc.package_kind,
       pc.fill_volume_l, pr.volume_in_l, pr.units_out, pr.volume_out_l, pr.loss_l,
       CASE WHEN pr.volume_in_l > 0 THEN round(100 * pr.loss_l / pr.volume_in_l, 2) END AS loss_pct,
       pc.expected_loss_pct, pr.abv_at_packaging, pr.co2_g_100ml, src.name AS source_vessel, u.display_name AS posted_by,
       (SELECT jsonb_agg(jsonb_build_object('item_code', i.code, 'qty_base', m.qty_base, 'mode', m.mode, 'lot', ml.lot_number) ORDER BY i.code)
          FROM app.packaging_run_materials m JOIN app.items i ON i.id = m.item_id LEFT JOIN app.lots ml ON ml.id = m.lot_id
         WHERE m.packaging_run_id = pr.id) AS materials
  FROM app.packaging_runs pr
  JOIN app.batches b ON b.id = pr.batch_id
  JOIN app.products p ON p.id = b.product_id
  JOIN app.packaging_configurations pc ON pc.id = pr.packaging_configuration_id
  LEFT JOIN app.vessels src ON src.id = pr.source_vessel_id
  LEFT JOIN app.users u ON u.id = pr.posted_by
 WHERE (CAST(%(run_id)s AS bigint) IS NULL OR pr.id = %(run_id)s)
   AND (CAST(%(run_id)s AS bigint) IS NOT NULL OR CAST(%(status)s AS text) IS NULL OR pr.status = %(status)s)
   AND (CAST(%(package_kind)s AS text) IS NULL OR pc.package_kind = %(package_kind)s)
   AND (CAST(%(batch_id)s AS bigint) IS NULL OR pr.batch_id = %(batch_id)s)
 ORDER BY pr.run_on DESC, COALESCE(pr.finished_at, pr.posted_at, pr.created_at) DESC, pr.id DESC
 LIMIT %(lim)s
"""

RACK_STOCK = """
SELECT fs.*, fl.unit_volume_l
  FROM app.v_fifo_stock fs
  LEFT JOIN app.finished_lots fl ON fl.lot_id = fs.lot_id
 WHERE (CAST(%(rack)s AS text) IS NULL OR fs.rack_number = %(rack)s)
   AND (CAST(%(area_id)s AS bigint) IS NULL OR fs.area_location_id = %(area_id)s)
   AND (CAST(%(product_id)s AS bigint) IS NULL OR fs.product_id = %(product_id)s)
   AND (CAST(%(item_id)s AS bigint) IS NULL OR fs.item_id = %(item_id)s)
   AND (CAST(%(item_class)s AS text) IS NULL OR fs.item_class = %(item_class)s)
   AND (NOT %(released_only)s OR fs.quality_status = 'released')
 ORDER BY fs.item_code, fs.fifo_rank NULLS LAST, fs.stock_date NULLS LAST, fs.lot_number, fs.area_name, fs.rack_sort NULLS FIRST
 LIMIT %(lim)s OFFSET %(off)s
"""

FINISHED_STOCK = """
SELECT fs.product_name, fs.package_name, fs.package_kind, pc.units_per_case, pc.fill_volume_l, fs.lot_number, fs.batch_number, l.quality_status,
       fs.tax_class, fs.abv, fs.packaged_on, fs.best_before_on, fs.location_name, fs.area_name, fs.rack_number, fs.tax_state, fs.units_on_hand, fs.units_available,
       fs.volume_on_hand_l, fs.unit_cost
  FROM app.v_finished_stock fs
  JOIN app.lots l ON l.id = fs.lot_id
  JOIN app.finished_lots fl ON fl.lot_id = fs.lot_id
  JOIN app.packaging_configurations pc ON pc.id = fl.packaging_configuration_id
 WHERE fs.units_on_hand <> 0
   AND (CAST(%(product_id)s AS bigint) IS NULL OR fs.product_id = %(product_id)s)
   AND (CAST(%(package_kind)s AS text) IS NULL OR fs.package_kind = %(package_kind)s)
   AND (CAST(%(location_id)s AS bigint) IS NULL OR fs.location_id = %(location_id)s OR fs.area_location_id = %(location_id)s)
   AND (%(include_unreleased)s OR l.quality_status = 'released')
 ORDER BY fs.product_name, fs.package_name, fs.packaged_on, fs.lot_number, fs.location_name
 LIMIT %(lim)s OFFSET %(off)s
"""

PACKAGING_DEMAND = """
SELECT 'batch' AS source, b.number AS ref, p.id AS product_id, p.name AS product_name, b.current_volume_l AS volume_l,
       COALESCE(po.planned_package_on, (now() AT TIME ZONE %(tz)s)::date) AS package_on, b.current_stage_code AS stage
  FROM app.batches b JOIN app.products p ON p.id = b.product_id LEFT JOIN app.production_orders po ON po.id = b.production_order_id
 WHERE b.status = 'active' AND b.current_volume_l > 0
   AND (b.current_stage_code IN ('carbonate','package') OR po.planned_package_on BETWEEN %(date_from)s AND %(date_to)s)
UNION ALL
SELECT 'production_order', po.number, p.id, p.name, po.planned_volume_l, po.planned_package_on, po.status
  FROM app.production_orders po JOIN app.products p ON p.id = po.product_id
 WHERE po.status IN ('planned','released','in_progress') AND po.planned_package_on BETWEEN %(date_from)s AND %(date_to)s
   AND NOT EXISTS (SELECT 1 FROM app.batches b WHERE b.production_order_id = po.id AND b.status = 'active')
 ORDER BY 6, 2
"""

PACKAGE_CONFIGS = """
SELECT pc.id, pc.product_id, pc.name, pc.package_kind, pc.fill_volume_l, pc.units_per_case, pc.expected_loss_pct,
       (SELECT jsonb_agg(jsonb_build_object('item_id', bl.item_id, 'item_code', i.code, 'item_name', i.name, 'unit', i.base_unit_code,
                         'qty_per_unit_base', bl.qty_per_unit_base) ORDER BY i.code)
          FROM app.packaging_bom_lines bl JOIN app.items i ON i.id = bl.item_id WHERE bl.configuration_id = pc.id) AS bom
  FROM app.packaging_configurations pc
 WHERE pc.active AND pc.product_id = ANY(%(product_ids)s)
 ORDER BY pc.product_id, pc.name
"""

FINISHED_LOTS_FOR_BATCH = """
SELECT l.lot_number, pc.name AS package_name, pc.package_kind, fl.packaged_on, fl.units_packaged, fl.unit_volume_l, fl.tax_class,
       fl.tax_class_source, fl.abv, fl.best_before_on, l.quality_status, pr.number AS packaging_run, pr.status AS run_status,
       COALESCE((SELECT sum(b.qty_on_hand) FROM app.inventory_balances b WHERE b.lot_id = l.id), 0) AS units_on_hand
  FROM app.finished_lots fl
  JOIN app.lots l ON l.id = fl.lot_id
  JOIN app.packaging_configurations pc ON pc.id = fl.packaging_configuration_id
  JOIN app.packaging_runs pr ON pr.id = fl.packaging_run_id
 WHERE fl.batch_id = %(batch_id)s
 ORDER BY fl.packaged_on, l.lot_number
"""

FINISHED_LOT = """
SELECT fl.lot_id, l.lot_number, fl.batch_id, b.number AS batch_number, p.name AS product_name, p.beverage_type, p.contains_other_fruit,
       p.contains_flavoring, p.intended_tax_class, rv.version_no AS recipe_version, b.status AS batch_status, pc.name AS package_name,
       pc.package_kind, fl.packaged_on, fl.units_packaged, fl.unit_volume_l, fl.abv, fl.co2_g_100ml, fl.fruit_share_pct, fl.tax_class,
       fl.tax_class_source, rc.name AS tax_override_reason, uo.display_name AS tax_override_by, fl.best_before_on, l.quality_status,
       pr.number AS packaging_run, fl.unit_cost, pc.units_per_case
  FROM app.finished_lots fl
  JOIN app.lots l ON l.id = fl.lot_id
  JOIN app.batches b ON b.id = fl.batch_id
  JOIN app.products p ON p.id = b.product_id
  LEFT JOIN app.recipe_versions rv ON rv.id = b.recipe_version_id
  JOIN app.packaging_configurations pc ON pc.id = fl.packaging_configuration_id
  JOIN app.packaging_runs pr ON pr.id = fl.packaging_run_id
  LEFT JOIN app.reason_codes rc ON rc.id = fl.tax_class_override_reason_code_id
  LEFT JOIN app.users uo ON uo.id = fl.tax_class_override_by
 WHERE fl.lot_id = %(lot_id)s
"""

TAX_RULE = """
SELECT params, effective_from FROM app.tax_class_rules WHERE beverage_type = %(beverage_type)s AND effective_from <= (now() AT TIME ZONE %(tz)s)::date
 ORDER BY effective_from DESC LIMIT 1
"""

DERIVE_TAX_CLASS = """
SELECT app.derive_tax_class(%(beverage_type)s, CAST(%(abv)s AS numeric), CAST(%(co2)s AS numeric), CAST(%(fruit)s AS numeric),
                            %(other_fruit)s, %(flavoring)s, false, (now() AT TIME ZONE %(tz)s)::date) AS tax_class
"""

KEG_FLEET = """
SELECT k.serial, k.size_l, k.state, k.ownership, k.holder_name, k.current_holder_kind, k.lot_number, k.fill_count, k.deposit_amount,
       k.last_moved_at, k.days_since_moved
  FROM app.v_keg_fleet k
 WHERE (CAST(%(state)s AS text) IS NULL OR k.state = %(state)s)
   AND (CAST(%(customer_id)s AS bigint) IS NULL OR (k.current_holder_kind = 'customer' AND k.current_holder_id = %(customer_id)s))
   AND (CAST(%(older_than_days)s AS int) IS NULL OR k.days_since_moved > %(older_than_days)s)
 ORDER BY k.days_since_moved DESC NULLS LAST, k.serial
 LIMIT %(lim)s OFFSET %(off)s
"""

KEG_SUMMARY = """
SELECT k.state, count(*) AS kegs, round(avg(k.days_since_moved), 1) AS avg_days_since_moved, max(k.days_since_moved) AS max_days_since_moved,
       sum(k.deposit_amount) AS deposits
  FROM app.v_keg_fleet k GROUP BY k.state ORDER BY count(*) DESC
"""

KEGS_BY_CUSTOMER = """
SELECT k.holder_name AS customer, count(*) AS kegs, max(k.days_since_moved) AS longest_out_days
  FROM app.v_keg_fleet k WHERE k.current_holder_kind = 'customer' GROUP BY k.holder_name ORDER BY count(*) DESC
"""

KEG = """
SELECT k.keg_id AS id, k.serial, k.size_l, k.state, k.ownership, k.holder_name, k.lot_number, k.fill_count, k.deposit_amount, k.last_moved_at,
       k.days_since_moved, kk.last_cleaned_at, kk.notes
  FROM app.v_keg_fleet k JOIN app.kegs kk ON kk.id = k.keg_id WHERE k.keg_id = %(keg_id)s
"""

KEG_MOVEMENTS = """
SELECT m.occurred_at, m.event, l.lot_number, c.name AS customer, loc.name AS location, r.number AS removal, m.amount, u.display_name AS actor, m.note
  FROM app.keg_movements m
  LEFT JOIN app.lots l ON l.id = m.lot_id
  LEFT JOIN app.customers c ON c.id = m.customer_id
  LEFT JOIN app.locations loc ON loc.id = m.location_id
  LEFT JOIN app.removals r ON r.id = m.removal_id
  LEFT JOIN app.users u ON u.id = m.actor_id
 WHERE m.keg_id = %(keg_id)s
 ORDER BY m.occurred_at DESC, m.id DESC
 LIMIT %(lim)s OFFSET %(off)s
"""

# ---------------------------------------------------------------------------- quality

OUT_OF_SPEC = """
SELECT r.taken_at, b.number AS batch_number, p.name AS product_name, r.stage_code, r.measurement_type_code AS measurement, mt.unit, r.value,
       sp.min_value AS spec_min, sp.max_value AS spec_max, sp.target_value AS spec_target, r.is_lab, u.display_name AS analyst, r.note,
       EXISTS (SELECT 1 FROM app.readings r2 WHERE r2.target_kind = 'batch' AND r2.target_id = r.target_id
                 AND r2.measurement_type_code = r.measurement_type_code AND r2.taken_at > r.taken_at AND r2.spec_result = 'pass') AS later_pass
  FROM app.readings r
  JOIN app.batches b ON r.target_kind = 'batch' AND b.id = r.target_id
  JOIN app.products p ON p.id = b.product_id
  JOIN app.measurement_types mt ON mt.code = r.measurement_type_code
  LEFT JOIN app.specs sp ON sp.id = r.spec_id
  LEFT JOIN app.users u ON u.id = r.analyst_id
 WHERE r.spec_result = 'fail'
   AND (CAST(%(batch_id)s AS bigint) IS NULL OR r.target_id = %(batch_id)s)
   AND r.taken_at >= now() - make_interval(days => CAST(%(days)s AS int))
 ORDER BY r.taken_at DESC, r.id DESC
 LIMIT %(lim)s OFFSET %(off)s
"""

RELEASE_QUEUE_BATCHES = """
SELECT b.number AS batch_number, p.name AS product_name, b.current_stage_code, st.name AS stage_name, b.current_volume_l,
       COALESCE(cur.entered_at, b.started_at) AS stage_since,
       (SELECT count(*) FROM app.readings r WHERE r.target_kind = 'batch' AND r.target_id = b.id AND r.spec_result = 'fail'
          AND r.taken_at >= COALESCE(cur.entered_at, b.started_at)) AS failing_readings_in_stage,
       (SELECT jsonb_object_agg(x.measurement_type_code, jsonb_build_object('value', x.value, 'spec_result', x.spec_result, 'taken_at', x.taken_at))
          FROM (SELECT DISTINCT ON (r.measurement_type_code) r.measurement_type_code, r.value, r.spec_result, r.taken_at FROM app.readings r
                 WHERE r.target_kind = 'batch' AND r.target_id = b.id AND r.measurement_type_code IN ('abv','co2','free_so2','ph')
                 ORDER BY r.measurement_type_code, r.taken_at DESC, r.id DESC) x) AS latest_readings,
       (SELECT jsonb_build_object('verdict', sr.verdict, 'panel_on', sr.panel_on) FROM app.sensory_records sr
         WHERE sr.target_kind = 'batch' AND sr.target_id = b.id ORDER BY sr.panel_on DESC, sr.id DESC LIMIT 1) AS latest_sensory,
       (SELECT jsonb_build_object('to_status', d.to_status, 'decided_at', d.decided_at) FROM app.release_decisions d
         WHERE d.target_kind = 'batch' AND d.target_id = b.id ORDER BY d.decided_at DESC, d.id DESC LIMIT 1) AS last_decision
  FROM app.batches b
  JOIN app.products p ON p.id = b.product_id
  JOIN app.stages st ON st.code = b.current_stage_code
  LEFT JOIN LATERAL (SELECT max(se.entered_at) AS entered_at FROM app.stage_events se WHERE se.batch_id = b.id AND se.stage_code = b.current_stage_code) cur ON true
 WHERE b.status = 'active' AND b.current_stage_code IN ('carbonate','back_sweeten','blend','maturation')
   AND NOT EXISTS (SELECT 1 FROM app.release_decisions d WHERE d.target_kind = 'batch' AND d.target_id = b.id AND d.to_status = 'released'
                    AND d.decided_at > COALESCE(cur.entered_at, b.started_at))
 ORDER BY b.number
"""

RELEASE_QUEUE_LOTS = """
SELECT l.lot_number, i.code AS item_code, i.name AS item_name, i.item_class, i.base_unit_code, l.quality_status,
       COALESCE(l.received_on, l.produced_on) AS received_on, s.name AS supplier_name,
       EXISTS (SELECT 1 FROM app.certificates_of_analysis c WHERE c.lot_id = l.id) AS has_coa,
       (SELECT sum(ib.qty_on_hand) FROM app.inventory_balances ib WHERE ib.lot_id = l.id) AS qty_on_hand,
       (SELECT jsonb_object_agg(x.measurement_type_code, x.value) FROM (SELECT DISTINCT ON (r.measurement_type_code) r.measurement_type_code, r.value
          FROM app.readings r WHERE r.target_kind = 'lot' AND r.target_id = l.id ORDER BY r.measurement_type_code, r.taken_at DESC) x) AS latest_readings
  FROM app.lots l JOIN app.items i ON i.id = l.item_id LEFT JOIN app.suppliers s ON s.id = l.supplier_id
 WHERE l.quality_status IN ('quarantine','hold')
   AND COALESCE((SELECT sum(ib.qty_on_hand) FROM app.inventory_balances ib WHERE ib.lot_id = l.id), 0) > 0
 ORDER BY COALESCE(l.received_on, l.produced_on) NULLS LAST, l.id
"""

SENSORY = """
SELECT s.panel_on, COALESCE(u.display_name, s.panelist_name) AS panelist, s.sample_code, s.verdict, s.attributes, s.faults, s.comment
  FROM app.sensory_records s LEFT JOIN app.users u ON u.id = s.panelist_id
 WHERE s.target_kind = %(target_kind)s AND s.target_id = %(target_id)s
   AND (CAST(%(date_from)s AS date) IS NULL OR s.panel_on >= %(date_from)s)
 ORDER BY s.panel_on DESC, s.id DESC
 LIMIT %(lim)s OFFSET %(off)s
"""

# ---------------------------------------------------------------------------- costing

BATCH_COST = """
SELECT c.number, c.status, c.material_cost, c.packaging_cost, c.overhead_cost, c.total_cost, c.starting_volume_l, c.packaged_volume_l,
       c.units_out, c.liquid_cost_per_l, c.standard_cost_total, c.variance_to_standard, rv.standard_cost_per_l, rv.version_no AS recipe_version,
       rv.target_batch_volume_l
  FROM app.v_batch_costs c LEFT JOIN app.recipe_versions rv ON rv.id = c.recipe_version_id
 WHERE c.batch_id = %(batch_id)s
"""

BATCH_MATERIAL_COSTS = """
SELECT item_name, item_class, lot_number, purpose, qty_base, base_unit_code, unit_cost_base, round(cost, 2) AS cost
  FROM app.v_batch_material_costs WHERE batch_id = %(batch_id)s ORDER BY cost DESC
"""

PARENT_LIQUID_COSTS = """
SELECT pb.number AS parent_batch, bl.event_kind, bl.volume_l, c.liquid_cost_per_l, round(bl.volume_l * c.liquid_cost_per_l, 2) AS inherited_cost
  FROM app.batch_lineage bl JOIN app.batches pb ON pb.id = bl.parent_batch_id JOIN app.v_batch_costs c ON c.batch_id = bl.parent_batch_id
 WHERE bl.child_batch_id = %(batch_id)s
"""

BATCH_PACKAGE_COSTS = """
SELECT pc.name AS package_name, pc.package_kind, pc.fill_volume_l, pc.units_per_case, sum(fl.units_packaged) AS units_packaged,
       avg(fl.unit_cost) AS recorded_unit_cost,
       (SELECT sum(bl.qty_per_unit_base * COALESCE(i.standard_cost_per_base, 0)) FROM app.packaging_bom_lines bl JOIN app.items i ON i.id = bl.item_id
         WHERE bl.configuration_id = pc.id) AS packaging_cost_per_unit,
       jsonb_agg(l.lot_number ORDER BY l.lot_number) AS lots
  FROM app.finished_lots fl JOIN app.packaging_configurations pc ON pc.id = fl.packaging_configuration_id JOIN app.lots l ON l.id = fl.lot_id
 WHERE fl.batch_id = %(batch_id)s
 GROUP BY pc.id, pc.name, pc.package_kind, pc.fill_volume_l, pc.units_per_case
"""

VALUATION = """
WITH bal AS (
    SELECT t.item_id, t.lot_id, t.location_id, sum(t.qty_base) AS qty
      FROM app.inventory_transactions t
     WHERE t.occurred_at < (CAST(%(as_of)s AS date) + 1)::timestamp AT TIME ZONE %(tz)s
     GROUP BY t.item_id, t.lot_id, t.location_id HAVING sum(t.qty_base) <> 0
)
SELECT CASE %(group_by)s WHEN 'item_class' THEN i.item_class WHEN 'tax_state' THEN loc.tax_state WHEN 'location' THEN loc.name
            WHEN 'item' THEN i.code || ' ' || i.name ELSE i.item_class || ' / ' || loc.tax_state END AS grp,
       CASE WHEN count(DISTINCT i.base_unit_code) = 1 THEN min(i.base_unit_code) END AS unit,
       sum(b.qty) AS qty, count(DISTINCT b.lot_id) AS lots,
       round(sum(b.qty * CASE WHEN i.costing_method = 'standard' THEN COALESCE(i.standard_cost_per_base, 0) ELSE l.unit_cost_base END), 2) AS value,
       sum(b.qty * fl.unit_volume_l) AS packaged_volume_l
  FROM bal b JOIN app.items i ON i.id = b.item_id JOIN app.lots l ON l.id = b.lot_id JOIN app.locations loc ON loc.id = b.location_id
  LEFT JOIN app.finished_lots fl ON fl.lot_id = b.lot_id
 WHERE (CAST(%(premises_id)s AS bigint) IS NULL OR loc.premises_id = %(premises_id)s)
 GROUP BY 1 ORDER BY value DESC NULLS LAST
"""

BULK_VALUE = """
SELECT sum(o.volume_l) AS volume_l, round(sum(o.volume_l * COALESCE(c.liquid_cost_per_l, 0)), 2) AS value, count(DISTINCT b.id) AS batches
  FROM app.vessel_occupancies o JOIN app.batches b ON o.occupant_kind = 'batch' AND b.id = o.occupant_id
  JOIN app.vessels v ON v.id = o.vessel_id
  LEFT JOIN app.v_batch_costs c ON c.batch_id = b.id
 WHERE o.from_at < (CAST(%(as_of)s AS date) + 1)::timestamp AT TIME ZONE %(tz)s
   AND (o.to_at IS NULL OR o.to_at >= (CAST(%(as_of)s AS date) + 1)::timestamp AT TIME ZONE %(tz)s)
   AND (CAST(%(premises_id)s AS bigint) IS NULL OR v.premises_id = %(premises_id)s)
"""

STAGE_YIELDS = """
SELECT y.number AS batch_number, y.stage_code, y.stage_name, y.entered_at, y.left_at, y.volume_in_l, y.volume_out_l, y.actual_loss_pct,
       y.expected_loss_pct, y.recorded_loss_l,
       CASE WHEN y.actual_loss_pct IS NOT NULL AND y.expected_loss_pct IS NOT NULL THEN round(y.actual_loss_pct - y.expected_loss_pct, 2) END AS variance_pct
  FROM app.v_batch_stage_yields y
 WHERE y.batch_id = ANY(%(batch_ids)s)
 ORDER BY y.number, y.entered_at
"""

LAST_BATCHES_FOR_PRODUCT = """
SELECT id, number FROM app.batches WHERE product_id = %(product_id)s ORDER BY started_at DESC LIMIT %(n)s
"""

JUICE_YIELD = """
SELECT extract(year FROM y.run_on)::int AS season_year, COALESCE(y.variety, '(no variety recorded)') AS variety, count(DISTINCT y.press_run_id) AS press_runs,
       sum(y.fruit_kg) AS fruit_kg, sum(y.juice_l_attributed) AS juice_l,
       (sum(y.juice_l_attributed) / 3.785411784) / NULLIF(sum(y.fruit_kg) / 907.18474, 0) AS gal_per_ton,
       (sum(y.juice_l_attributed) / 3.785411784) / NULLIF(sum(y.fruit_kg) / 19.05087954, 0) AS gal_per_bushel,
       sum(y.juice_l_attributed) / NULLIF(sum(y.fruit_kg), 0) AS l_per_kg,
       jsonb_agg(DISTINCT y.number) AS press_run_numbers
  FROM app.v_press_run_yields y
 WHERE (CAST(%(season_year)s AS int) IS NULL OR extract(year FROM y.run_on) = %(season_year)s)
   AND (CAST(%(variety_pat)s AS text) IS NULL OR y.variety ILIKE %(variety_pat)s)
 GROUP BY 1, 2 ORDER BY 1 DESC, 3 DESC
"""

# ---------------------------------------------------------------------------- compliance and traceability

TTB_LINES = "SELECT section, line_code, label, source, match FROM app.ttb_line_map WHERE form_code = '5120.17' ORDER BY section, display_order"

BATCH_CLASS = "COALESCE(b.tax_class_override, b.tax_class_derived, p.intended_tax_class)"

PERIOD_PRODUCED = f"""
SELECT {BATCH_CLASS} AS tax_class, sum(s.volume_in_l) AS liters, count(*) AS events
  FROM app.stage_events s JOIN app.batches b ON b.id = s.batch_id JOIN app.products p ON p.id = b.product_id
 WHERE s.stage_code = 'pitch' AND s.volume_in_l IS NOT NULL
   AND (CAST(%(premises_id)s AS bigint) IS NULL OR b.premises_id = %(premises_id)s)
   AND s.entered_at >= %(start)s::timestamp AT TIME ZONE %(tz)s AND s.entered_at < (%(end)s::date + 1)::timestamp AT TIME ZONE %(tz)s
 GROUP BY 1
"""

PERIOD_BOTTLED = """
SELECT fl.tax_class, sum(t.qty_base * fl.unit_volume_l) AS liters, count(*) AS events
  FROM app.inventory_transactions t JOIN app.finished_lots fl ON fl.lot_id = t.lot_id
 WHERE t.ttb_category = 'bottled' AND t.qty_base > 0
   AND (CAST(%(premises_id)s AS bigint) IS NULL OR t.premises_id = %(premises_id)s)
   AND t.occurred_at >= %(start)s::timestamp AT TIME ZONE %(tz)s AND t.occurred_at < (%(end)s::date + 1)::timestamp AT TIME ZONE %(tz)s
 GROUP BY 1
"""

PERIOD_REMOVALS = """
SELECT r.destination_kind, COALESCE(rl.tax_class, r.tax_class) AS tax_class, sum(rl.volume_l) AS liters, count(DISTINCT r.id) AS removals
  FROM app.removals r JOIN app.removal_lines rl ON rl.removal_id = r.id
 WHERE r.posted_at IS NOT NULL
   AND (CAST(%(premises_id)s AS bigint) IS NULL OR r.premises_id = %(premises_id)s)
   AND r.removed_at >= %(start)s::timestamp AT TIME ZONE %(tz)s AND r.removed_at < (%(end)s::date + 1)::timestamp AT TIME ZONE %(tz)s
 GROUP BY 1, 2
"""

PERIOD_TAX = """
SELECT COALESCE(r.tax_class, '') AS tax_class, sum(r.tax_amount) AS tax_amount, sum(r.wine_gallons) FILTER (WHERE r.tax_determined) AS taxed_gallons
  FROM app.removals r
 WHERE r.posted_at IS NOT NULL AND r.tax_amount IS NOT NULL
   AND (CAST(%(premises_id)s AS bigint) IS NULL OR r.premises_id = %(premises_id)s)
   AND r.removed_at >= %(start)s::timestamp AT TIME ZONE %(tz)s AND r.removed_at < (%(end)s::date + 1)::timestamp AT TIME ZONE %(tz)s
 GROUP BY 1
"""

PERIOD_LOSSES = f"""
SELECT le.ttb_category, le.target_kind, CASE WHEN le.target_kind = 'batch' THEN {BATCH_CLASS} ELSE fl.tax_class END AS tax_class,
       sum(CASE WHEN le.unit_code = 'ea' THEN le.qty_base * fl.unit_volume_l
                ELSE le.qty_base * COALESCE((SELECT to_base_factor FROM app.units u WHERE u.code = le.unit_code AND u.dimension = 'volume'), 0) END) AS liters,
       count(*) AS events
  FROM app.loss_events le
  LEFT JOIN app.batches b ON le.target_kind = 'batch' AND b.id = le.target_id
  LEFT JOIN app.products p ON p.id = b.product_id
  LEFT JOIN app.finished_lots fl ON le.target_kind = 'lot' AND fl.lot_id = le.target_id
 WHERE le.reportable
   AND (CAST(%(premises_id)s AS bigint) IS NULL OR le.premises_id = %(premises_id)s)
   AND le.occurred_at >= %(start)s::timestamp AT TIME ZONE %(tz)s AND le.occurred_at < (%(end)s::date + 1)::timestamp AT TIME ZONE %(tz)s
 GROUP BY 1, 2, 3
"""

PERIOD_REPORTS_COVERING = """
SELECT number, form_code, period_start, period_end, status FROM app.period_reports
 WHERE period_start <= %(end)s AND period_end >= %(start)s AND (CAST(%(premises_id)s AS bigint) IS NULL OR premises_id = %(premises_id)s)
 ORDER BY period_start
"""

BULK_AT = f"""
SELECT {BATCH_CLASS} AS tax_class, sum(o.volume_l) AS liters, count(DISTINCT b.id) AS batches, jsonb_agg(DISTINCT b.number) AS batch_numbers
  FROM app.vessel_occupancies o JOIN app.batches b ON o.occupant_kind = 'batch' AND b.id = o.occupant_id JOIN app.products p ON p.id = b.product_id
 WHERE (CAST(%(premises_id)s AS bigint) IS NULL OR b.premises_id = %(premises_id)s)
   AND o.from_at < %(at)s AND (o.to_at IS NULL OR o.to_at >= %(at)s)
 GROUP BY 1
"""

JUICE_AT = """
SELECT i.name AS item_name, sum(o.volume_l) AS liters, jsonb_agg(DISTINCT l.lot_number) AS lot_numbers
  FROM app.vessel_occupancies o JOIN app.lots l ON o.occupant_kind = 'lot' AND l.id = o.occupant_id JOIN app.items i ON i.id = l.item_id
  JOIN app.vessels v ON v.id = o.vessel_id
 WHERE (CAST(%(premises_id)s AS bigint) IS NULL OR v.premises_id = %(premises_id)s)
   AND o.from_at < %(at)s AND (o.to_at IS NULL OR o.to_at >= %(at)s)
 GROUP BY 1
"""

PACKAGED_AT = """
SELECT fl.tax_class, t.tax_state, sum(t.qty_base) AS units, sum(t.qty_base * fl.unit_volume_l) AS liters, count(DISTINCT t.lot_id) AS lots
  FROM app.inventory_transactions t JOIN app.finished_lots fl ON fl.lot_id = t.lot_id
 WHERE (CAST(%(premises_id)s AS bigint) IS NULL OR t.premises_id = %(premises_id)s) AND t.occurred_at < %(at)s
 GROUP BY 1, 2 HAVING sum(t.qty_base) <> 0
"""

REMOVALS = """
SELECT r.number, r.removed_at, r.direction, r.destination_kind, c.name AS customer, r.status, r.reference, fl_.name AS from_location,
       tl_.name AS to_location, r.tax_determined, r.wine_gallons, r.tax_class, r.tax_rate_per_gal, r.cbma_credit_per_gal, r.tax_amount,
       rv.number AS reversed_by, u.display_name AS posted_by,
       (SELECT jsonb_agg(jsonb_build_object('lot_number', l.lot_number, 'product', p.name, 'package', pc.name, 'units', rl.units,
                         'volume_l', rl.volume_l, 'gal', round(rl.volume_l / 3.785411784, 3), 'keg', k.serial, 'tax_class', rl.tax_class) ORDER BY rl.id)
          FROM app.removal_lines rl JOIN app.lots l ON l.id = rl.lot_id LEFT JOIN app.finished_lots fl ON fl.lot_id = l.id
          LEFT JOIN app.batches b ON b.id = fl.batch_id LEFT JOIN app.products p ON p.id = b.product_id
          LEFT JOIN app.packaging_configurations pc ON pc.id = fl.packaging_configuration_id LEFT JOIN app.kegs k ON k.id = rl.keg_id
         WHERE rl.removal_id = r.id) AS lines
  FROM app.removals r
  LEFT JOIN app.customers c ON c.id = r.customer_id
  LEFT JOIN app.locations fl_ ON fl_.id = r.from_location_id
  LEFT JOIN app.locations tl_ ON tl_.id = r.to_location_id
  LEFT JOIN app.removals rv ON rv.id = r.reversed_by_id
  LEFT JOIN app.users u ON u.id = r.posted_by
 WHERE (CAST(%(destination_kind)s AS text) IS NULL OR r.destination_kind = %(destination_kind)s)
   AND (CAST(%(customer_id)s AS bigint) IS NULL OR r.customer_id = %(customer_id)s)
   AND (CAST(%(status)s AS text) IS NULL OR r.status = %(status)s)
   AND (CAST(%(date_from)s AS date) IS NULL OR (r.removed_at AT TIME ZONE %(tz)s)::date >= %(date_from)s)
   AND (CAST(%(date_to)s AS date) IS NULL OR (r.removed_at AT TIME ZONE %(tz)s)::date <= %(date_to)s)
 ORDER BY r.removed_at DESC, r.id DESC
 LIMIT %(lim)s OFFSET %(off)s
"""

REMOVAL_TOTALS = """
WITH r AS (
    SELECT r.id, r.destination_kind, r.status, r.tax_amount,
           (SELECT sum(rl.units) FROM app.removal_lines rl WHERE rl.removal_id = r.id) AS units,
           (SELECT sum(rl.volume_l) FROM app.removal_lines rl WHERE rl.removal_id = r.id) AS volume_l
      FROM app.removals r
     WHERE (CAST(%(destination_kind)s AS text) IS NULL OR r.destination_kind = %(destination_kind)s)
       AND (CAST(%(customer_id)s AS bigint) IS NULL OR r.customer_id = %(customer_id)s)
       AND (CAST(%(status)s AS text) IS NULL OR r.status = %(status)s)
       AND (CAST(%(date_from)s AS date) IS NULL OR (r.removed_at AT TIME ZONE %(tz)s)::date >= %(date_from)s)
       AND (CAST(%(date_to)s AS date) IS NULL OR (r.removed_at AT TIME ZONE %(tz)s)::date <= %(date_to)s)
)
SELECT destination_kind, status, count(*) AS removals, sum(units) AS units, sum(volume_l) AS volume_l, sum(tax_amount) AS tax_amount
  FROM r GROUP BY 1, 2 ORDER BY 1, 2
"""

REPORT_LIST = """
SELECT r.number, p.name AS premises_name, r.form_code, r.period_start, r.period_end, r.status, r.generated_at, r.filed_at,
       r.totals->'total_gallons' AS total_gallons, r.totals->'total_tax' AS total_tax
  FROM app.period_reports r JOIN app.premises p ON p.id = r.premises_id
 WHERE (CAST(%(premises_id)s AS bigint) IS NULL OR r.premises_id = %(premises_id)s)
 ORDER BY r.period_start DESC, r.id DESC
 LIMIT %(lim)s OFFSET %(off)s
"""

REPORT_FOR_PERIOD = """
SELECT r.id FROM app.period_reports r
 WHERE (CAST(%(premises_id)s AS bigint) IS NULL OR r.premises_id = %(premises_id)s) AND r.period_start = %(start)s AND r.period_end = %(end)s
 ORDER BY r.id DESC LIMIT 1
"""

REPORT_HEADER = """
SELECT r.number, p.name AS premises_name, p.registry_number, r.form_code, r.period_start, r.period_end, r.status, r.generated_at,
       ug.display_name AS generated_by, r.finalized_at, r.filed_at, uf.display_name AS filed_by, r.totals - 'previous_lines' AS totals, r.notes
  FROM app.period_reports r JOIN app.premises p ON p.id = r.premises_id
  LEFT JOIN app.users ug ON ug.id = r.generated_by LEFT JOIN app.users uf ON uf.id = r.filed_by
 WHERE r.id = %(report_id)s
"""

REPORT_LINES = """
SELECT l.section, l.line_code, l.label, l.tax_class, l.value, l.unit, jsonb_array_length(l.source_ids) AS source_rows, l.source_ids,
       l.is_adjustment, m.source
  FROM app.period_report_lines l JOIN app.period_reports r ON r.id = l.report_id
  LEFT JOIN app.ttb_line_map m ON m.form_code = r.form_code AND m.section = l.section AND m.line_code = l.line_code
 WHERE l.report_id = %(report_id)s
 ORDER BY l.section, m.display_order, l.tax_class NULLS LAST
"""

TRACE_FORWARD = """
SELECT t.level, t.kind, t.label, t.detail, c.name AS customer_name,
       CASE WHEN t.kind = 'finished_lot' THEN COALESCE((SELECT sum(b.qty_on_hand) FROM app.inventory_balances b WHERE b.lot_id = t.id), 0) END AS units_on_hand,
       CASE WHEN t.kind = 'finished_lot' THEN (SELECT l.quality_status FROM app.lots l WHERE l.id = t.id) END AS quality_status
  FROM app.trace_forward(%(lot_id)s) t
  LEFT JOIN app.customers c ON t.kind = 'removal' AND c.id = NULLIF(t.detail->>'customer_id', '')::bigint
 ORDER BY t.level, t.kind, t.label
"""

KEGS_HOLDING_LOTS = """
SELECT k.serial, k.state, k.holder_name, k.lot_number FROM app.v_keg_fleet k WHERE k.lot_number = ANY(%(lot_numbers)s) ORDER BY k.serial
"""

SIBLING_FINISHED_LOTS = """
SELECT DISTINCT l.lot_number, b.number AS batch_number, pc.name AS package_name, fl.packaged_on,
       COALESCE((SELECT sum(ib.qty_on_hand) FROM app.inventory_balances ib WHERE ib.lot_id = l.id), 0) AS units_on_hand,
       CASE WHEN fl.batch_id = ANY(%(batch_ids)s) THEN 'same batch tree' ELSE 'shares an ingredient lot' END AS relation
  FROM app.finished_lots fl JOIN app.lots l ON l.id = fl.lot_id JOIN app.batches b ON b.id = fl.batch_id
  JOIN app.packaging_configurations pc ON pc.id = fl.packaging_configuration_id
 WHERE fl.lot_id <> COALESCE(CAST(%(exclude_lot_id)s AS bigint), 0)
   AND (fl.batch_id = ANY(%(batch_ids)s)
        OR fl.batch_id IN (SELECT c.batch_id FROM app.consumptions c WHERE c.lot_id = ANY(%(lot_ids)s) AND c.batch_id IS NOT NULL))
 ORDER BY 2, 1
"""

# ---------------------------------------------------------------------------- schema summary (startup)

SCHEMA_COLUMNS = """
SELECT c.table_name, t.table_type, string_agg(c.column_name || ':' || CASE
         WHEN c.data_type IN ('bigint','integer','smallint') THEN 'int' WHEN c.data_type = 'numeric' THEN 'num'
         WHEN c.data_type LIKE 'timestamp%%' THEN 'ts' WHEN c.data_type = 'date' THEN 'date' WHEN c.data_type = 'boolean' THEN 'bool'
         WHEN c.data_type IN ('jsonb','json') THEN 'json' WHEN c.data_type = 'uuid' THEN 'uuid' WHEN c.data_type = 'ARRAY' THEN 'array'
         ELSE 'text' END, ', ' ORDER BY c.ordinal_position) AS columns
  FROM information_schema.columns c JOIN information_schema.tables t ON t.table_schema = c.table_schema AND t.table_name = c.table_name
 WHERE c.table_schema = 'app' AND c.column_name NOT IN ('created_at', 'updated_at')
 GROUP BY c.table_name, t.table_type
 ORDER BY t.table_type, c.table_name
"""

LOT_IDS_BY_NUMBER = "SELECT id FROM app.lots WHERE lot_number = ANY(%(n)s)"
BATCH_IDS_BY_NUMBER = "SELECT id FROM app.batches WHERE number = ANY(%(n)s)"

# Equipment scheduling (db/023, docs/16) ------------------------------------------------------------------------
EQUIPMENT_SCHEDULE = """
SELECT s.id, s.resource_kind, s.resource_name, s.resource_type, s.resource_status, s.capacity_l, s.kind, s.subject_kind, s.subject_number,
       s.subject_label, s.subject_status, s.role, s.starts_at, s.ends_at, s.all_day, s.local_from, s.local_to, s.shared, s.clash_count, s.notes
  FROM app.v_equipment_schedule s
 WHERE tstzrange(s.starts_at, s.ends_at) && tstzrange(%(starts)s::timestamptz, %(ends)s::timestamptz)
   AND (CAST(%(resource_kind)s AS text) IS NULL OR s.resource_kind = %(resource_kind)s)
   AND (CAST(%(resource_id)s AS bigint) IS NULL OR s.resource_id = %(resource_id)s)
   AND (CAST(%(resource_type)s AS text) IS NULL OR s.resource_type = %(resource_type)s)
   AND (CAST(%(premises_id)s AS bigint) IS NULL OR s.premises_id = %(premises_id)s)
   AND (CAST(%(subject_kind)s AS text) IS NULL OR (s.subject_kind = %(subject_kind)s AND s.subject_id = %(subject_id)s))
 ORDER BY s.resource_kind, s.resource_name, s.starts_at, s.id
 LIMIT %(lim)s OFFSET %(off)s
"""

EQUIPMENT_RESOURCES = """
SELECT r.resource_kind, r.resource_id, r.name, r.kind, r.premises_id, r.status, r.capacity_l
  FROM app.v_equipment_resources r
 WHERE r.active AND r.status <> 'out_of_service'
   AND (CAST(%(resource_kind)s AS text) IS NULL OR r.resource_kind = %(resource_kind)s)
   AND (CAST(%(resource_type)s AS text) IS NULL OR r.kind = %(resource_type)s)
   AND (CAST(%(premises_id)s AS bigint) IS NULL OR r.premises_id = %(premises_id)s)
   AND (CAST(%(min_capacity_l)s AS numeric) IS NULL OR r.capacity_l >= %(min_capacity_l)s)
 ORDER BY r.sort_group, r.name
"""

EQUIPMENT_BOOKED_WINDOWS = """
SELECT s.resource_kind, s.resource_id, s.starts_at, s.ends_at
  FROM app.v_equipment_schedule s
 WHERE tstzrange(s.starts_at, s.ends_at) && tstzrange(%(starts)s::timestamptz, %(ends)s::timestamptz)
 ORDER BY s.resource_kind, s.resource_id, s.starts_at
"""
