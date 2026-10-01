<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/purchase-orders/queries.php';

// Pattern A fragment: one PO line row. Called to add a row, and on item change to
// refresh the unit choices and default the supplier's unit and last price.
$user = require_role('receiving');
$pdo = db();
$n = preg_replace('/[^a-z0-9]/i', '', request_string('n', 20)) ?: 'n' . time();
$raw = $_GET['lines'][$n] ?? [];
$raw = is_array($raw) ? $raw : [];
$catalog = purchasing_item_catalog($pdo);
$itemId = (int) ($raw['item_id'] ?? 0);
$line = [
    'item_id' => $itemId ?: null,
    'qty_ordered' => (string) ($raw['qty_ordered'] ?? ''),
    'unit_price' => (string) ($raw['unit_price'] ?? ''),
    'expected_on' => (string) ($raw['expected_on'] ?? ''),
    'purchase_unit_code' => (string) ($raw['purchase_unit_code'] ?? ''),
];
if ($itemId && isset($catalog[$itemId])) {
    $supplierId = request_integer('supplier_id');
    $terms = $supplierId ? find_supplier_item_terms($pdo, $supplierId, $itemId) : null;
    if (!isset($catalog[$itemId]['units'][$line['purchase_unit_code']])) {
        $line['purchase_unit_code'] = $terms['purchase_unit_code'] ?? $catalog[$itemId]['base_unit_code'];
    }
    if ($line['unit_price'] === '' && $terms !== null && $terms['last_price'] !== null) {
        $line['unit_price'] = (string) (float) $terms['last_price'];
    }
}
echo view('purchase-orders/partials/form-line.php', ['n' => $n, 'line' => $line, 'catalog' => $catalog, 'lineErrors' => []]);
