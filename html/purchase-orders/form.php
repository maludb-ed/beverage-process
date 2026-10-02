<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/purchase-orders/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';

$user = require_role('receiving');
$pdo = db();
$id = request_integer('id');
$catalog = purchasing_item_catalog($pdo);
$suppliers = purchasing_supplier_options($pdo);
$premises = premises_options($pdo);

if ($id !== null) {
    $order = find_purchase_order($pdo, $id) ?? not_found('That purchase order does not exist.');
    if ($order['status'] !== 'draft') {
        hx_location('/purchase-orders/' . $id);
    }
    $lines = [];
    foreach (find_purchase_order_lines($pdo, $id) as $line) {
        $lines['n' . $line['line_no']] = $line;
    }
    $screen = 'purchase-order-edit';
} else {
    // Prefill: ?supplier=<name> resolves to an id; ?expected_on=YYYY-MM-DD; ?item=CODE and ?qty= fill the first line.
    $supplierName = request_string('supplier', 120);
    $supplierId = $supplierName !== '' ? (array_search(mb_strtolower($supplierName), array_map('mb_strtolower', $suppliers), true) ?: null) : request_integer('supplier_id');
    $expected = request_string('expected_on', 10);
    $order = ['supplier_id' => $supplierId, 'premises_id' => default_premises_id($pdo), 'expected_on' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $expected) ? $expected : null];
    // ?item=CODE (from the reorder report) pre-fills the first line with that item and its supplier terms.
    $itemCode = mb_strtolower(request_string('item', 40));
    $firstLine = [];
    foreach ($catalog as $itemId => $item) {
        if ($itemCode !== '' && mb_strtolower($item['code']) === $itemCode) {
            $terms = $supplierId ? find_supplier_item_terms($pdo, (int) $supplierId, $itemId) : null;
            // ?qty= (from suggested purchases) is in the supplier's purchase unit.
            $qty = request_string('qty', 12);
            $firstLine = ['item_id' => $itemId, 'purchase_unit_code' => $terms['purchase_unit_code'] ?? $item['base_unit_code'], 'unit_price' => $terms['last_price'] ?? '',
                          'qty_ordered' => is_numeric($qty) && (float) $qty > 0 ? $qty : '', 'expected_on' => $order['expected_on']];
            break;
        }
    }
    $lines = ['n1' => $firstLine];
    $screen = 'purchase-order-add';
}
log_screen_entered($screen, 'purchase_order', $id, $order['number'] ?? null);
render_screen($id ? 'Edit ' . $order['number'] : 'Add Purchase Order', $screen, view('purchase-orders/partials/form.php', [
    'order' => $order, 'lines' => $lines, 'errors' => [], 'lineErrors' => [], 'catalog' => $catalog, 'suppliers' => $suppliers, 'premises' => $premises,
]), 'purchase_order', $id);
