<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/counts/queries.php';

require_post();
verify_csrf();
$user = require_role('owner');
$pdo = db();
$id = request_integer('id') ?? not_found('That count does not exist.');

try {
    $pdo->beginTransaction();
    $before = find_count($pdo, $id, true) ?? not_found('That count does not exist.');
    $result = approve_count($pdo, $id, (int) $user['id']);
    log_activity($pdo, 'count_approved', 'count', $id, $before['number'], ['status' => $before['status']], ['status' => 'approved'] + $result, [], 'count-view');
    $pdo->commit();
    flash('success', 'Count ' . $before['number'] . ' approved: ' . count($result['variance_lines']) . ' correction(s) posted.');
    hx_trigger('countsChanged, inventoryChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('count approve failed: ' . $exception->getMessage());
    flash('error', !$exception instanceof PDOException && $exception instanceof RuntimeException ? $exception->getMessage() : (db_error_message($exception) ?? 'The count could not be updated.'));
}
hx_location('/counts/' . $id);
