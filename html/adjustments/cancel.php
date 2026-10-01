<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/adjustments/queries.php';

require_post();
verify_csrf();
$user = require_role('receiving');
$pdo = db();
$id = request_integer('id') ?? not_found('That adjustment does not exist.');
$adjustment = find_adjustment($pdo, $id) ?? not_found('That adjustment does not exist.');

try {
    $pdo->beginTransaction();
    if (!cancel_adjustment($pdo, $id, (int) $user['id'])) {
        throw new RuntimeException('Only a draft or pending adjustment can be cancelled.');
    }
    log_activity($pdo, 'adjustment_cancelled', 'adjustment', $id, $adjustment['number'], ['status' => $adjustment['status']], ['status' => 'cancelled'], [], 'adjustment-view');
    $pdo->commit();
    flash('success', 'Adjustment ' . $adjustment['number'] . ' cancelled.');
    hx_trigger('adjustmentsChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('adjustment cancel failed: ' . $exception->getMessage());
    flash('error', !$exception instanceof PDOException && $exception instanceof RuntimeException ? $exception->getMessage() : 'The adjustment could not be cancelled.');
}
hx_location('/adjustments/' . $id);
