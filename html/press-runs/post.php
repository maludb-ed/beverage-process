<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/press-runs/queries.php';

// The receipts posting sequence: lock, validate, write lots + ledger rows + occupancies
// through query functions, log inside the transaction, commit, navigate with HX-Trigger.
require_post();
verify_csrf();
$user = require_role('production');
$pdo = db();
$id = request_integer('id') ?? not_found('That press run does not exist.');

try {
    $pdo->beginTransaction();
    $posted = post_press_run($pdo, $id, (int) $user['id']);
    $run = $posted['run'];
    log_activity($pdo, 'press_run_posted', 'press_run', $id, $run['number'], ['status' => 'draft'], ['status' => 'posted', 'totals' => $posted['totals'], 'lots' => $posted['lots'], 'ledger_group_id' => $posted['ledger_group_id']],
        $posted['capacity_warnings'] !== [] ? ['capacity_warning' => true] : [], 'press-run-view');
    $pdo->commit();
    foreach ($posted['capacity_warnings'] as $warning) {
        flash('warning', $warning);
    }
    flash('success', 'Press run ' . $run['number'] . ' posted: ' . implode(', ', array_column($posted['lots'], 'lot_number')) . ' created.');
    hx_trigger('pressRunsChanged, lotsChanged, inventoryChanged, batchesChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('press run post failed: ' . $exception->getMessage());
    flash('error', $exception instanceof PDOException ? (db_error_message($exception) ?? (is_unique_violation($exception) ? 'A vessel was filled by someone else meanwhile; check the tank board.' : 'The press run could not be posted.')) : $exception->getMessage());
}
hx_location('/press-runs/' . $id);
