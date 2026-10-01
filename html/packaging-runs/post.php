<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/packaging-runs/queries.php';

// Posting transaction (copy of receipts/post.php): lock, validate, write through query functions, log, commit.
require_post();
verify_csrf();
$user = require_role('production');
$pdo = db();
$id = request_integer('id') ?? not_found('That packaging run does not exist.');

try {
    $pdo->beginTransaction();
    $run = find_packaging_run($pdo, $id, true) ?? not_found('That packaging run does not exist.');
    $posted = post_packaging_run($pdo, $id, (int) $user['id']);
    $details = ['batch' => $run['batch_number'], 'loss_classification' => $posted['loss_classification'], 'batch_packaged' => $posted['batch_packaged']];
    if ($posted['release_missing']) { $details['release_missing'] = true; }
    log_activity($pdo, 'packaging_run_posted', 'packaging_run', $id, $run['number'], ['status' => 'draft'],
        ['status' => 'posted', 'finished_lot' => $posted['finished_lot'], 'units_out' => $posted['units_out'], 'loss_l' => $posted['loss_l'], 'tax_class' => $posted['tax_class']],
        $details, 'packaging-run-view');
    $pdo->commit();
    flash('success', 'Packaging run ' . $run['number'] . ' posted: lot ' . $posted['finished_lot'] . ', ' . $posted['units_out'] . ' units, ' . str_replace('_', ' ', $posted['tax_class']) . '.');
    hx_trigger('packagingRunsChanged, finishedLotsChanged, lotsChanged, inventoryChanged, batchesChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('packaging run post failed: ' . $exception->getMessage());
    flash('error', !$exception instanceof PDOException && $exception instanceof RuntimeException ? $exception->getMessage() : (db_error_message($exception) ?? 'The packaging run could not be posted.'));
}
hx_location('/packaging-runs/' . $id);
