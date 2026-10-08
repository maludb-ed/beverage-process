<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/packaging-runs/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/reservations/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/fulfillment.php';

require_post();
verify_csrf();
$user = require_role('production');
$pdo = db();
$id = request_integer('id') ?? not_found('That packaging run does not exist.');
$run = find_packaging_run($pdo, $id) ?? not_found('That packaging run does not exist.');
try {
    $pdo->beginTransaction();
    $orderIds = orders_for_packaging_run($pdo, $id);
    if (!delete_packaging_run($pdo, $id)) {
        throw new RuntimeException('Only draft packaging runs can be deleted.');
    }
    foreach (cancel_subject_reservations($pdo, 'packaging_run', $id, (int) $user['id']) as $b) {
        log_activity($pdo, 'equipment_reservation_cancelled', 'reservation', (int) $b['id'], $run['number'], ['status' => 'booked'], ['status' => 'cancelled'], ['cause' => 'run_cancelled'], 'packaging-run-view');
    }
    log_activity($pdo, 'packaging_run_deleted', 'packaging_run', $id, $run['number'], ['status' => $run['status'], 'run_on' => $run['run_on']], null, [], 'packaging-run-view');
    orders_refresh_statuses($pdo, $orderIds, 'packaging-run-view');
    $pdo->commit();
    flash('success', 'Packaging run ' . $run['number'] . ' deleted.');
    hx_trigger('packagingRunsChanged');
    hx_location('/packaging-runs/');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('packaging run delete failed: ' . $exception->getMessage());
    flash('error', $exception instanceof PDOException ? (db_error_message($exception) ?? 'The packaging run could not be deleted.') : $exception->getMessage());
}
hx_location('/packaging-runs/' . $id);
