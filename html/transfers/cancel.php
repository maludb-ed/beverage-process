<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/transfers/queries.php';

require_post();
verify_csrf();
$user = require_role('receiving');
$pdo = db();
$id = request_integer('id') ?? not_found('That transfer does not exist.');
$transfer = find_transfer($pdo, $id) ?? not_found('That transfer does not exist.');

try {
    $pdo->beginTransaction();
    if (!cancel_transfer($pdo, $id, (int) $user['id'])) {
        throw new RuntimeException('Only a draft transfer can be cancelled.');
    }
    log_activity($pdo, 'transfer_cancelled', 'transfer', $id, $transfer['number'], ['status' => 'draft'], ['status' => 'cancelled'], [], 'transfer-view');
    $pdo->commit();
    flash('success', 'Transfer ' . $transfer['number'] . ' cancelled.');
    hx_trigger('transfersChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('transfer cancel failed: ' . $exception->getMessage());
    flash('error', !$exception instanceof PDOException && $exception instanceof RuntimeException ? $exception->getMessage() : 'The transfer could not be cancelled.');
}
hx_location('/transfers/' . $id);
