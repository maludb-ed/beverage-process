<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/queries.php';

// Pattern A fragment: one order line. Called to add a row, and on format change to
// default the list price and show what stock and bulk stand behind the format.
$user = require_role('sales');
$pdo = db();
$n = preg_replace('/[^a-z0-9]/i', '', request_string('n', 20)) ?: 'n' . time();
$raw = $_GET['lines'][$n] ?? [];
$raw = is_array($raw) ? $raw : [];
$configId = (int) ($raw['packaging_configuration_id'] ?? 0);
$catalog = order_format_catalog($pdo, $configId ? [$configId] : []);
$line = [
    'id' => ctype_digit((string) ($raw['id'] ?? '')) ? (int) $raw['id'] : null,
    'packaging_configuration_id' => $configId ?: null,
    'units_ordered' => (string) ($raw['units_ordered'] ?? ''),
    'unit_price' => (string) ($raw['unit_price'] ?? ''),
    'notes' => (string) ($raw['notes'] ?? ''),
];
// A change of format brings that format's list price.
if ($configId && isset($catalog[$configId]) && request_string('changed', 10) === 'format') {
    $price = $catalog[$configId]['default_unit_price'];
    $line['unit_price'] = $price === null ? '' : number_format((float) $price, 2, '.', '');
}
echo view('orders/partials/form-line.php', [
    'n' => $n, 'line' => $line, 'catalog' => $catalog, 'lineErrors' => [], 'canPrice' => user_can($user, 'sales'), 'orderId' => request_integer('order_id'),
    'availability' => $configId && isset($catalog[$configId]) ? order_format_availability($pdo, $configId, request_integer('order_id')) : null,
]);
