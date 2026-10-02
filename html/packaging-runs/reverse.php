<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/packaging-runs/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/fulfillment.php';

require_post();
verify_csrf();
$user = require_role('production');
$pdo = db();
$id = request_integer('id') ?? not_found('That packaging run does not exist.');

try {
    $pdo->beginTransaction();
    $run = find_packaging_run($pdo, $id, true) ?? not_found('That packaging run does not exist.');
    $before = ['status' => $run['status'], 'finished_lot' => $run['finished_lot_number'], 'volume_in_l' => $run['volume_in_l'], 'units_out' => $run['units_out']];
    $result = reverse_packaging_run($pdo, $id, (int) $user['id']);
    log_activity($pdo, 'packaging_run_reversed', 'packaging_run', $id, $run['number'], $before,
        ['status' => 'cancelled', 'finished_lot_status' => 'rejected', 'volume_returned_l' => $result['volume_in_l']], ['ledger_rows' => $result['ledger_rows'], 'batch' => $run['batch_number']], 'packaging-run-view');
    orders_refresh_statuses($pdo, orders_for_packaging_run($pdo, $id), 'packaging-run-view');
    $pdo->commit();
    flash('success', 'Packaging run ' . $run['number'] . ' reversed; lot ' . $result['finished_lot'] . ' is rejected.');
    hx_trigger('packagingRunsChanged, finishedLotsChanged, lotsChanged, inventoryChanged, batchesChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('packaging run reverse failed: ' . $exception->getMessage());
    flash('error', !$exception instanceof PDOException && $exception instanceof RuntimeException ? $exception->getMessage() : (db_error_message($exception) ?? 'The packaging run could not be reversed.'));
}
hx_location('/packaging-runs/' . $id);
