<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/adjustments/queries.php';

// Same shape as receipts/post.php: lock, validate, write ledger rows, log, commit, navigate.
require_post();
verify_csrf();
$user = require_role('receiving');
$pdo = db();
$id = request_integer('id') ?? not_found('That adjustment does not exist.');

try {
    $pdo->beginTransaction();
    $posted = post_adjustment($pdo, $id, (int) $user['id']);
    log_activity($pdo, 'adjustment_posted', 'adjustment', $id, $posted['number'], ['status' => 'draft'], ['status' => 'posted', 'lines' => $posted['lines'], 'group_id' => $posted['group_id']], [], 'adjustment-view');
    $pdo->commit();
    flash('success', 'Adjustment ' . $posted['number'] . ' posted: ' . count($posted['lines']) . ' line(s).');
    hx_trigger('adjustmentsChanged, inventoryChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('adjustment post failed: ' . $exception->getMessage());
    flash('error', !$exception instanceof PDOException && $exception instanceof RuntimeException ? $exception->getMessage() : (db_error_message($exception) ?? 'The adjustment could not be posted.'));
}
hx_location('/adjustments/' . $id);
