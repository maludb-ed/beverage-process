<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/receipts/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/receipts/validation.php';
require_once dirname(__DIR__, 2) . '/app/features/purchase-orders/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';

$user = require_role('receiving');
$pdo = db();
$id = request_integer('id');
$lines = [];

if ($id !== null) {
    $receipt = find_receipt($pdo, $id) ?? not_found('That receipt does not exist.');
    if ($receipt['status'] !== 'draft') {
        hx_location('/receipts/' . $id);
    }
    foreach (find_receipt_lines($pdo, $id) as $line) {
        $line['unit_price'] = (float) $line['qty_received'] > 0 ? round((float) $line['unit_cost_base'] * (float) $line['qty_base'] / (float) $line['qty_received'], 4) : '';
        $line['weigh_tag'] = $line['weigh_tag_id'] !== null ? $line : [];
        $lines['n' . $line['line_no']] = $line;
    }
    $screen = 'receipt-edit';
} else {
    $receipt = ['premises_id' => default_premises_id($pdo)];
    // Prefill: ?po_number=PO-00001 loads that order's outstanding lines; ?supplier=<name>.
    $poNumber = request_string('po_number', 40);
    $order = $poNumber !== '' ? find_purchase_order_by_number($pdo, $poNumber) : null;
    if ($order !== null && in_array($order['status'], ['open', 'partial'], true)) {
        $receipt += ['purchase_order_id' => (int) $order['id'], 'supplier_id' => (int) $order['supplier_id'], 'premises_id' => (int) $order['premises_id']];
        foreach (find_open_po_lines($pdo, (int) $order['id']) as $poLine) {
            $lines['p' . $poLine['line_id']] = [
                'purchase_order_line_id' => (int) $poLine['line_id'], 'item_id' => (int) $poLine['item_id'], 'purchase_unit_code' => $poLine['purchase_unit_code'],
                'qty_received' => round(max(0, (float) $poLine['qty_outstanding_base']) / (float) $poLine['to_base_factor'], 3), 'unit_price' => (float) $poLine['unit_price'],
            ];
        }
    } else {
        $suppliers = purchasing_supplier_options($pdo);
        $supplierName = mb_strtolower(request_string('supplier', 120));
        $match = $supplierName !== '' ? array_search($supplierName, array_map('mb_strtolower', $suppliers), true) : false;
        $receipt['supplier_id'] = $match !== false ? $match : null;
    }
    if ($lines === []) {
        $lines = ['n1' => []];
    }
    $screen = 'receipt-add';
}
$catalog = purchasing_item_catalog($pdo);
foreach ($lines as &$line) {   // default expiry from the item's shelf life
    if (empty($line['expires_on']) && !empty($line['item_id']) && !empty($catalog[(int) $line['item_id']]['shelf_life_days'])) {
        $line['expires_on'] = (new DateTimeImmutable(today()))->modify('+' . (int) $catalog[(int) $line['item_id']]['shelf_life_days'] . ' days')->format('Y-m-d');
    }
}
unset($line);
log_screen_entered($screen, 'goods_receipt', $id, $receipt['number'] ?? null);
render_screen($id ? 'Edit ' . $receipt['number'] : 'Add Receipt', $screen, view('receipts/partials/form.php', [
    'receipt' => $receipt, 'lines' => $lines, 'errors' => [], 'lineErrors' => [], 'catalog' => $catalog,
    'suppliers' => purchasing_supplier_options($pdo), 'premises' => premises_options($pdo),
    'locations' => receiving_location_options($pdo, $receipt['premises_id'] ?? null), 'openOrders' => all_open_purchase_order_options($pdo, $receipt['purchase_order_id'] ?? null),
]), 'goods_receipt', $id);
