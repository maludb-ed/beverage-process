<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/purchase-orders/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/customers/queries.php';

$user = require_role('sales');
$pdo = db();
$id = request_integer('id');

if ($id !== null) {
    $order = find_order($pdo, $id) ?? not_found('That order does not exist.');
    if (!in_array($order['status'], ['draft', 'confirmed'], true)) {
        hx_location('/orders/' . $id);
    }
    $lines = [];
    foreach (find_order_lines($pdo, $id) as $line) {
        $lines['n' . $line['line_no']] = $line;
    }
    $catalog = order_format_catalog($pdo, array_column($lines, 'packaging_configuration_id'));
    $customers = order_customer_options($pdo, (int) $order['customer_id']);
    $screen = 'order-edit';
} else {
    // Prefill: ?customer=<name> resolves to an id.
    $customers = order_customer_options($pdo);
    $customerName = mb_strtolower(request_string('customer', 120));
    $customerId = null;
    foreach ($customers as $cid => $customer) {
        if ($customerName !== '' && mb_strtolower($customer['name']) === $customerName) { $customerId = $cid; break; }
    }
    $customerId ??= request_integer('customer_id');
    $customerId = $customerId !== null && isset($customers[$customerId]) ? $customerId : null;
    $order = ['customer_id' => $customerId, 'premises_id' => default_premises_id($pdo), 'ordered_on' => today(),
              'destination_kind' => order_default_destination($customerId ? $customers[$customerId] : null), 'status' => 'draft'];
    $lines = ['n1' => []];
    $catalog = order_format_catalog($pdo);
    $screen = 'order-add';
}
log_screen_entered($screen, 'sales_order', $id, $order['number'] ?? null);
render_screen($id ? 'Edit ' . $order['number'] : 'New Order', $screen, view('orders/partials/form.php', [
    'order' => $order, 'lines' => $lines, 'errors' => [], 'lineErrors' => [], 'catalog' => $catalog, 'customers' => $customers,
    'premises' => premises_options($pdo), 'canPrice' => user_can($user, 'sales'),
]), 'sales_order', $id);
