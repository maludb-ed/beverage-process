<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/customers/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/queries.php';

$user = require_login();
$pdo = db();
$id = request_integer('id') ?? not_found('That customer does not exist.');
$customer = find_customer($pdo, $id) ?? not_found('That customer does not exist.');
log_screen_entered('customer-view', 'customer', $id, $customer['name']);
render_screen($customer['name'], 'customer-view', view('customers/partials/view.php', [
    'customer' => $customer, 'removals' => find_customer_removals($pdo, $id), 'kegs' => find_customer_kegs_out($pdo, $id), 'user' => $user, 'history' => customer_history($pdo, $id),
    'orders' => find_orders($pdo, '', '-requested_on', 1, null, $id),
]), 'customer', $id);
