<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/receipts/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/receipts/validation.php';
require_once dirname(__DIR__, 2) . '/app/features/purchase-orders/queries.php';

// Pattern A fragment: the outstanding lines of the chosen order as receipt lines
// (quantities in the order's purchase units), or one blank line when none is chosen.
$user = require_role('receiving');
$pdo = db();
$poId = request_integer('purchase_order_id');
$catalog = purchasing_item_catalog($pdo);
$html = '';
if ($poId !== null) {
    foreach (find_open_po_lines($pdo, $poId) as $poLine) {
        $html .= view('receipts/partials/form-line.php', ['n' => 'p' . $poLine['line_id'], 'catalog' => $catalog, 'lineErrors' => [], 'line' => [
            'purchase_order_line_id' => (int) $poLine['line_id'], 'item_id' => (int) $poLine['item_id'], 'purchase_unit_code' => $poLine['purchase_unit_code'],
            'qty_received' => round(max(0, (float) $poLine['qty_outstanding_base']) / (float) $poLine['to_base_factor'], 3), 'unit_price' => (float) $poLine['unit_price'],
        ]]);
    }
}
echo $html !== '' ? $html : view('receipts/partials/form-line.php', ['n' => 'n1', 'line' => [], 'catalog' => $catalog, 'lineErrors' => []]);
