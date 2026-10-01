<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/production-orders/queries.php';

require_post();
verify_csrf();
$user = require_role('production');
$pdo = db();
$id = request_integer('id') ?? not_found('That production order does not exist.');

try {
    $pdo->beginTransaction();
    $order = find_production_order($pdo, $id, true) ?? not_found('That production order does not exist.');
    $after = close_production_order($pdo, $id, (int) $user['id']);
    log_activity($pdo, 'production_order_closed', 'production_order', $id, $order['number'], ['status' => $order['status']], $after, [], 'production-order-view');
    $pdo->commit();
    flash('success', 'Production order ' . $order['number'] . ' closed.');
    hx_trigger('productionOrdersChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($exception->getMessage());
    flash('error', !$exception instanceof PDOException && $exception instanceof RuntimeException ? $exception->getMessage() : (db_error_message($exception) ?? 'The production order could not be updated.'));
}
hx_location('/production-orders/' . $id);
