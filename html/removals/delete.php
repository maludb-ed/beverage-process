<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/removals/queries.php';

require_post();
verify_csrf();
$user = require_role('compliance');
$pdo = db();
$id = request_integer('id') ?? not_found('That removal does not exist.');
$removal = find_removal($pdo, $id) ?? not_found('That removal does not exist.');
try {
    $pdo->beginTransaction();
    if (!delete_removal($pdo, $id)) {
        throw new RuntimeException('Only a draft removal can be deleted; reverse a posted one.');
    }
    log_activity($pdo, 'removal_deleted', 'removal', $id, $removal['number'], array_intersect_key($removal, array_flip(['number', 'direction', 'destination_kind', 'customer_id', 'from_location_id', 'to_location_id', 'removed_at'])), null, [], 'removal-view');
    $pdo->commit();
    flash('success', $removal['number'] . ' deleted.');
    hx_trigger('removalsChanged');
    hx_location('/removals/');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('removal delete failed: ' . $exception->getMessage());
    flash('error', !$exception instanceof PDOException && $exception instanceof RuntimeException ? $exception->getMessage() : 'The removal could not be deleted.');
}
hx_location('/removals/' . $id);
