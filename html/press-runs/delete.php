<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/press-runs/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/reservations/queries.php';

require_post();
verify_csrf();
$user = require_role('production');
$pdo = db();
$id = request_integer('id') ?? not_found('That press run does not exist.');
$run = find_press_run($pdo, $id) ?? not_found('That press run does not exist.');
try {
    $pdo->beginTransaction();
    if (!delete_press_run($pdo, $id)) {
        throw new RuntimeException('Only draft press runs can be deleted.');
    }
    foreach (cancel_subject_reservations($pdo, 'press_run', $id, (int) $user['id']) as $b) {
        log_activity($pdo, 'equipment_reservation_cancelled', 'reservation', (int) $b['id'], $run['number'], ['status' => 'booked'], ['status' => 'cancelled'], ['cause' => 'run_cancelled'], 'press-run-view');
    }
    log_activity($pdo, 'press_run_deleted', 'press_run', $id, $run['number'], ['status' => $run['status'], 'run_on' => $run['run_on']], null, [], 'press-run-view');
    $pdo->commit();
    flash('success', 'Press run ' . $run['number'] . ' deleted.');
    hx_trigger('pressRunsChanged');
    hx_location('/press-runs/');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('press run delete failed: ' . $exception->getMessage());
    flash('error', $exception instanceof PDOException ? (db_error_message($exception) ?? 'The press run could not be deleted.') : $exception->getMessage());
}
hx_location('/press-runs/' . $id);
