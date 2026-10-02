<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/standing.php';
require_once dirname(__DIR__, 2) . '/app/features/purchase-orders/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';

// /orders/standing/new (?customer= prefills) and, through standing-edit.php, /orders/standing/{n}/edit.
$user = require_role('sales');
$pdo = db();
$id = request_integer('sub_id');
if ($id !== null) {
    $standing = find_standing_order($pdo, $id) ?? not_found('That standing order does not exist.');
    $lines = [];
    foreach (find_standing_order_lines($pdo, $id) as $line) {
        $lines['n' . $line['line_no']] = $line;
    }
    $customers = order_customer_options($pdo, (int) $standing['customer_id']);
    $catalog = order_format_catalog($pdo, array_column($lines, 'packaging_configuration_id'));
    $screen = 'standing-order-edit';
} else {
    $customers = order_customer_options($pdo);
    $name = mb_strtolower(request_string('customer', 120));
    $customerId = null;
    foreach ($customers as $cid => $customer) {
        if ($name !== '' && mb_strtolower($customer['name']) === $name) { $customerId = $cid; }
    }
    $standing = ['customer_id' => $customerId, 'premises_id' => default_premises_id($pdo), 'frequency' => 'weekly', 'weekday' => 5, 'interval_weeks' => 2,
                 'day_of_month' => 1, 'starts_on' => today()];
    $lines = ['n1' => []];
    $catalog = order_format_catalog($pdo);
    $screen = 'standing-order-add';
}
log_screen_entered($screen, 'standing_order', $id, $standing['number'] ?? null);
render_screen($id ? 'Edit ' . $standing['number'] : 'New Standing Order', $screen, view('orders/partials/standing-form.php', [
    'standing' => $standing, 'lines' => $lines, 'errors' => [], 'lineErrors' => [], 'catalog' => $catalog, 'customers' => $customers, 'premises' => premises_options($pdo),
]), 'standing_order', $id);
