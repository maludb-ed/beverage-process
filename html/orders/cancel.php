<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/queries.php';

require_post();
verify_csrf();
$user = require_role('sales');
$pdo = db();
$id = request_integer('id') ?? not_found('That order does not exist.');
$order = find_order($pdo, $id) ?? not_found('That order does not exist.');
$reason = request_string('cancel_reason', 500);
if ($reason === '') {
    flash('error', 'Give a reason to cancel the order.');
    hx_location('/orders/' . $id);
}
try {
    $pdo->beginTransaction();
    $after = cancel_order($pdo, $id, $reason, (int) $user['id']);
    log_activity($pdo, 'order_cancelled', 'sales_order', $id, $order['number'], ['status' => $order['status']], $after, ['customer' => $order['customer_name']], 'order-view');
    $pdo->commit();
    flash('success', 'Order ' . $order['number'] . ' cancelled.');
    hx_trigger('ordersChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($exception->getMessage());
    flash('error', !$exception instanceof PDOException ? $exception->getMessage() : 'The order could not be updated.');
}
hx_location('/orders/' . $id);
