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
    $result = submit_count($pdo, $id, (int) $user['id']);
    log_activity($pdo, 'count_submitted', 'count', $id, $before['number'], ['status' => $before['status']], ['status' => 'review'] + $result, [], 'count-view');
    $pdo->commit();
    flash('success', 'Count ' . $before['number'] . ' submitted for review.');
    hx_trigger('countsChanged, inventoryChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('count submit failed: ' . $exception->getMessage());
    flash('error', !$exception instanceof PDOException && $exception instanceof RuntimeException ? $exception->getMessage() : (db_error_message($exception) ?? 'The count could not be updated.'));
}
hx_location('/counts/' . $id);
