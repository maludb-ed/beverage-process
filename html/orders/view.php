<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/queries.php';

$user = require_login();
$pdo = db();
$id = request_integer('id') ?? not_found('That order does not exist.');
$order = find_order($pdo, $id) ?? not_found('That order does not exist.');
log_screen_entered('order-view', 'sales_order', $id, $order['number']);
render_screen($order['number'], 'order-view', view('orders/partials/view.php', [
    'order' => $order, 'lines' => find_order_lines($pdo, $id), 'runs' => find_order_packaging_runs($pdo, $id),
    'removals' => find_order_removals($pdo, $id), 'user' => $user, 'canPrice' => user_can($user, 'sales'),
]), 'sales_order', $id);
