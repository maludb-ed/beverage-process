<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/counts/queries.php';

require_post();
verify_csrf();
$user = require_role('receiving');
$pdo = db();
$id = request_integer('id') ?? not_found('That count does not exist.');

try {
    $pdo->beginTransaction();
    $before = find_count($pdo, $id, true) ?? not_found('That count does not exist.');
    if (!cancel_count($pdo, $id, (int) $user['id'])) {
        throw new RuntimeException('Count ' . $before['number'] . ' is ' . $before['status'] . ' and cannot be cancelled.');
    }
    $result = [];
    log_activity($pdo, 'count_cancelled', 'count', $id, $before['number'], ['status' => $before['status']], ['status' => 'cancelled'], [], 'count-view');
    $pdo->commit();
    flash('success', 'Count ' . $before['number'] . ' cancelled.');
    hx_trigger('countsChanged, inventoryChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('count cancel failed: ' . $exception->getMessage());
    flash('error', !$exception instanceof PDOException && $exception instanceof RuntimeException ? $exception->getMessage() : (db_error_message($exception) ?? 'The count could not be updated.'));
}
hx_location('/counts/' . $id);
