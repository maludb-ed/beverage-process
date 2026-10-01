<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/transfers/queries.php';

// Same shape as receipts/post.php: lock, validate, write ledger rows, log, commit, navigate.
require_post();
verify_csrf();
$user = require_role('receiving');
$pdo = db();
$id = request_integer('id') ?? not_found('That transfer does not exist.');

try {
    $pdo->beginTransaction();
    $posted = post_transfer($pdo, $id, (int) $user['id']);
    log_activity($pdo, 'transfer_posted', 'transfer', $id, $posted['number'], ['status' => 'draft'], ['status' => 'posted', 'lines' => $posted['lines'], 'group_ids' => $posted['groups']], [], 'transfer-view');
    $pdo->commit();
    flash('success', 'Transfer ' . $posted['number'] . ' posted: ' . count($posted['lines']) . ' line(s) moved.');
    hx_trigger('transfersChanged, inventoryChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('transfer post failed: ' . $exception->getMessage());
    flash('error', !$exception instanceof PDOException && $exception instanceof RuntimeException ? $exception->getMessage() : (db_error_message($exception) ?? 'The transfer could not be posted.'));
}
hx_location('/transfers/' . $id);
