<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/standing.php';

// Turn one date of a standing order into a confirmed customer order.
require_post();
verify_csrf();
$user = require_role('sales');
$pdo = db();
$id = request_integer('sub_id') ?? not_found('That standing order does not exist.');
$standing = find_standing_order($pdo, $id) ?? not_found('That standing order does not exist.');
$occursOn = post_date('occurs_on');
try {
    if (!is_string($occursOn)) {
        throw new RuntimeException('Choose the date to order.');
    }
    $pdo->beginTransaction();
    $order = create_order_from_standing($pdo, $standing, $occursOn, (int) $user['id']);
    log_activity($pdo, 'order_created_from_standing', 'sales_order', (int) $order['id'], $order['number'], null, $order,
        ['standing_order' => $standing['number'], 'occurs_on' => $occursOn, 'customer' => $standing['customer_name']], 'standing-order-view');
    $pdo->commit();
    flash('success', 'Order ' . $order['number'] . ' created for ' . format_date($occursOn) . '. Change its lines if this delivery differs.');
    hx_trigger('ordersChanged');
    hx_location('/orders/' . $order['id']);
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($exception->getMessage());
    flash('error', $exception instanceof PDOException ? (is_unique_violation($exception) ? 'That date already has an order.' : 'The order could not be created.') : $exception->getMessage());
}
hx_location('/orders/standing/' . $id);
