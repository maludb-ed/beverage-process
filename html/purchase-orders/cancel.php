<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/purchase-orders/queries.php';

require_post();
verify_csrf();
$user = require_role('receiving');
$pdo = db();
$id = request_integer('id') ?? not_found('That purchase order does not exist.');
$order = find_purchase_order($pdo, $id) ?? not_found('That purchase order does not exist.');
try {
    $pdo->beginTransaction();
    $after = cancel_purchase_order($pdo, $id);
    log_activity($pdo, 'po_cancelled', 'purchase_order', $id, $order['number'], ['status' => $order['status']], $after, [], 'purchase-order-view');
    $pdo->commit();
    flash('success', 'Purchase order ' . $order['number'] . ' cancelled.');
    hx_trigger('purchaseOrdersChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($exception->getMessage());
    flash('error', !$exception instanceof PDOException && $exception instanceof RuntimeException ? $exception->getMessage() : 'The purchase order could not be updated.');
}
hx_location('/purchase-orders/' . $id);
