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
    $after = cancel_production_order($pdo, $id, (int) $user['id']);
    $bookings = cancel_subject_reservations($pdo, 'production_order', $id, (int) $user['id']);
    foreach ($bookings as $b) {
        log_activity($pdo, 'equipment_reservation_cancelled', 'reservation', (int) $b['id'], $order['number'], ['status' => 'booked'], ['status' => 'cancelled'], ['cause' => 'run_cancelled'], 'production-order-view');
    }
    log_activity($pdo, 'production_order_cancelled', 'production_order', $id, $order['number'], ['status' => $order['status']], $after + ['bookings_cancelled' => count($bookings)], [], 'production-order-view');
    $pdo->commit();
    flash('success', 'Production order ' . $order['number'] . ' cancelled.');
    hx_trigger('productionOrdersChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($exception->getMessage());
    flash('error', !$exception instanceof PDOException && $exception instanceof RuntimeException ? $exception->getMessage() : (db_error_message($exception) ?? 'The production order could not be updated.'));
}
hx_location('/production-orders/' . $id);
