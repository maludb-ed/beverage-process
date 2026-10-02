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
try {
    $pdo->beginTransaction();
    $after = confirm_order($pdo, $id, (int) $user['id']);
    log_activity($pdo, 'order_confirmed', 'sales_order', $id, $order['number'], ['status' => $order['status']], $after, ['customer' => $order['customer_name']], 'order-view');
    $pdo->commit();
    flash('success', 'Order ' . $order['number'] . ' confirmed.');
    hx_trigger('ordersChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($exception->getMessage());
    flash('error', !$exception instanceof PDOException ? $exception->getMessage() : 'The order could not be updated.');
}
hx_location('/orders/' . $id);
