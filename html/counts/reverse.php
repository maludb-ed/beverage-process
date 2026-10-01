<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/counts/queries.php';

// Undo: compensating ledger rows for every row of the document, then the document is cancelled.
require_post();
verify_csrf();
$user = require_role('owner');
$pdo = db();
$id = request_integer('id') ?? not_found('That count does not exist.');

try {
    $pdo->beginTransaction();
    $document = find_count($pdo, $id, true) ?? not_found('That count does not exist.');
    if ($document['status'] !== 'approved') {
        throw new RuntimeException($document['number'] . ' is ' . $document['status'] . ' and cannot be reversed.');
    }
    $rows = reverse_document_ledger($pdo, 'counts', 'count', $id, (int) $user['id']);
    $pdo->prepare("UPDATE app.inventory_counts SET status = 'cancelled' WHERE id = :id")->execute(['id' => $id]);
    log_activity($pdo, 'count_reversed', 'count', $id, $document['number'], ['status' => 'approved'], ['status' => 'cancelled', 'reversal_rows' => array_column($rows, 'id'), 'group_id' => $rows[0]['group_id'] ?? null], [], 'count-view');
    $pdo->commit();
    flash('success', $document['number'] . ' reversed: ' . count($rows) . ' compensating ledger row(s) posted.');
    hx_trigger('countsChanged, inventoryChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('count reverse failed: ' . $exception->getMessage());
    flash('error', !$exception instanceof PDOException && $exception instanceof RuntimeException ? $exception->getMessage() : (db_error_message($exception) ?? 'The reversal could not be posted.'));
}
hx_location('/counts/' . $id);
