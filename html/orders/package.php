<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/fulfillment.php';

// Draft packaging runs for order lines: one order (/orders/{id}/package) or every open line of a format (/orders/package?format=).
$user = require_role('sales', 'production');
$pdo = db();
$id = request_integer('id');
$formatId = request_integer('format');
$order = null;
if ($id !== null) {
    $order = find_order($pdo, $id) ?? not_found('That order does not exist.');
    if (!in_array($order['status'], ['confirmed', 'in_fulfillment'], true)) {
        flash('error', 'Only a confirmed or in-fulfillment order can be packaged.');
        hx_location('/orders/' . $id);
    }
    $lines = array_filter(find_order_lines($pdo, $id), static fn($l) => (int) $l['units_open'] > 0);
    $plan = orders_package_plan($pdo, array_column($lines, 'packaging_configuration_id'), array_column($lines, 'id'));
} elseif ($formatId !== null) {
    $plan = orders_package_plan($pdo, [$formatId]);
} else {
    hx_location('/orders/to-package');
}
log_screen_entered('order-package', $order ? 'sales_order' : 'packaging_configuration', $order ? $id : $formatId, $order['number'] ?? null);
render_screen('Package for orders', 'order-package', view('orders/partials/package.php', [
    'order' => $order, 'formatId' => $formatId, 'plan' => $plan, 'input' => [], 'errors' => [],
]), $order ? 'sales_order' : null, $order ? $id : null);
