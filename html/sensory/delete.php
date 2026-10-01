<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/sensory/queries.php';

require_post();
verify_csrf();
$user = require_role('quality');
$pdo = db();
$id = request_integer('id') ?? not_found('That panel record does not exist.');
$record = find_sensory_record($pdo, $id) ?? not_found('That panel record does not exist.');
try {
    $pdo->beginTransaction();
    if (!delete_sensory_record($pdo, $id, (int) $user['id'])) {
        throw new RuntimeException('You can delete only your own panel records, on the day they were recorded.');
    }
    log_activity($pdo, 'sensory_deleted', 'sensory_record', $id, $record['target_number'], ['verdict' => $record['verdict'], 'panel_on' => $record['panel_on']], null, [], 'sensory-list');
    $pdo->commit();
    flash('success', 'Panel record deleted.');
    hx_trigger('sensoryChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('sensory delete failed: ' . $exception->getMessage());
    flash('error', !$exception instanceof PDOException && $exception instanceof RuntimeException ? $exception->getMessage() : (db_error_message($exception) ?? 'The panel record could not be deleted.'));
}
hx_location('/sensory/');
