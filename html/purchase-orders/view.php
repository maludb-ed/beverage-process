<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/purchase-orders/queries.php';

$user = require_login();
$pdo = db();
$id = request_integer('id') ?? not_found('That purchase order does not exist.');
$order = find_purchase_order($pdo, $id) ?? not_found('That purchase order does not exist.');
log_screen_entered('purchase-order-view', 'purchase_order', $id, $order['number']);
render_screen($order['number'], 'purchase-order-view', view('purchase-orders/partials/view.php', [
    'order' => $order, 'lines' => find_purchase_order_lines($pdo, $id), 'receipts' => find_receipts_for_po($pdo, $id),
    'user' => $user, 'shortReasons' => short_close_reason_options($pdo),
]), 'purchase_order', $id);
