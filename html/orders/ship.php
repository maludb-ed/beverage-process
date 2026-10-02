<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/fulfillment.php';

// Draft a shipment (removal) for what is on hand; compliance reviews and posts it on the removal screen.
require_post();
verify_csrf();
$user = require_role('sales', 'compliance');
$pdo = db();
$id = request_integer('id') ?? not_found('That order does not exist.');
$order = find_order($pdo, $id) ?? not_found('That order does not exist.');
try {
    $pdo->beginTransaction();
    [$removal, $shipped, $short] = orders_ship($pdo, $order, (int) $user['id']);
    log_activity($pdo, 'removal_created', 'removal', (int) $removal['id'], $removal['number'], null, $removal + ['lines' => $shipped], ['order' => $order['number']], 'order-view');
    log_activity($pdo, 'order_shipment_created', 'sales_order', $id, $order['number'], null, ['removal' => $removal['number'], 'units' => $shipped], ['short' => $short], 'order-view');
    order_refresh_status($pdo, $id, 'order-view');
    $pdo->commit();
    flash('success', 'Draft shipment ' . $removal['number'] . ' for ' . array_sum($shipped) . ' units' . ($short !== [] ? '; ' . array_sum($short) . ' units are not on hand yet and stay open' : '')
        . '. Check it and post it to ship.');
    hx_trigger('removalsChanged, ordersChanged');
    hx_location('/removals/' . $removal['id']);
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($exception->getMessage());
    flash('error', !$exception instanceof PDOException ? $exception->getMessage() : (db_error_message($exception) ?? 'The shipment could not be created.'));
}
hx_location('/orders/' . $id);
