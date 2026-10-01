<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/adjustments/queries.php';

require_post();
verify_csrf();
$user = require_role('owner');
$pdo = db();
$id = request_integer('id') ?? not_found('That adjustment does not exist.');

try {
    $pdo->beginTransaction();
    $before = find_adjustment($pdo, $id, true) ?? not_found('That adjustment does not exist.');
    $approved = approve_adjustment($pdo, $id, (int) $user['id']);
    log_activity($pdo, 'adjustment_approved', 'adjustment', $id, $before['number'], ['status' => $before['status']], ['status' => $approved['status'], 'approved_by' => $approved['approved_by']], [], 'adjustment-view');
    $pdo->commit();
    flash('success', 'Adjustment ' . $before['number'] . ' approved. It can now be posted.');
    hx_trigger('adjustmentsChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('adjustment approve failed: ' . $exception->getMessage());
    flash('error', !$exception instanceof PDOException && $exception instanceof RuntimeException ? $exception->getMessage() : 'The adjustment could not be approved.');
}
hx_location('/adjustments/' . $id);
