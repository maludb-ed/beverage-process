<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/receipts/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/receipts/validation.php';
require_once dirname(__DIR__, 2) . '/app/features/purchase-orders/queries.php';

// Pattern A fragment: one receipt line. Added by "Add line"; re-rendered on item change
// to refresh units, show or hide the weigh tag, and default unit, price and expiry.
$user = require_role('receiving');
$pdo = db();
$n = preg_replace('/[^a-z0-9]/i', '', request_string('n', 20)) ?: 'n' . time();
$raw = is_array($_GET['lines'][$n] ?? null) ? $_GET['lines'][$n] : [];
$catalog = purchasing_item_catalog($pdo);
$itemId = (int) ($raw['item_id'] ?? 0);
$line = array_intersect_key($raw, array_flip(['purchase_order_line_id', 'item_id', 'qty_received', 'purchase_unit_code', 'unit_price', 'expires_on', 'supplier_lot_number', 'discrepancy_kind', 'discrepancy_note', 'notes']));
$line['weigh_tag'] = is_array($raw['weigh_tag'] ?? null) ? $raw['weigh_tag'] + ['brix_at_receipt' => $raw['weigh_tag']['brix'] ?? null] : [];
if ($itemId && isset($catalog[$itemId])) {
    $item = $catalog[$itemId];
    $supplierId = request_integer('supplier_id');
    $terms = $supplierId ? find_supplier_item_terms($pdo, $supplierId, $itemId) : null;
    if (!isset($item['units'][(string) ($line['purchase_unit_code'] ?? '')])) {
        $line['purchase_unit_code'] = $terms['purchase_unit_code'] ?? $item['base_unit_code'];
    }
    if (($line['unit_price'] ?? '') === '' && $terms !== null && $terms['last_price'] !== null) {
        $line['unit_price'] = (string) (float) $terms['last_price'];
    }
    if (($line['expires_on'] ?? '') === '' && $item['shelf_life_days']) {
        $line['expires_on'] = (new DateTimeImmutable(today()))->modify('+' . (int) $item['shelf_life_days'] . ' days')->format('Y-m-d');
    }
}
echo view('receipts/partials/form-line.php', ['n' => $n, 'line' => $line, 'catalog' => $catalog, 'lineErrors' => []]);
