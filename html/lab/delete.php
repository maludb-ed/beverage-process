<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/lab/queries.php';

require_post();
verify_csrf();
$user = require_role('quality');
$pdo = db();
$id = request_integer('id') ?? not_found('That reading does not exist.');
$reading = find_reading($pdo, $id) ?? not_found('That reading does not exist.');
try {
    $pdo->beginTransaction();
    if (!delete_reading($pdo, $id, (int) $user['id'])) {
        throw new RuntimeException('You can delete only your own readings, on the day they were taken.');
    }
    log_activity($pdo, 'lab_reading_deleted', 'reading', $id, $reading['target_number'] . ' ' . $reading['measurement_name'],
        ['measurement' => $reading['measurement_type_code'], 'value' => (float) $reading['value'], 'spec_result' => $reading['spec_result']], null, [], 'lab-list');
    $pdo->commit();
    flash('success', 'Reading deleted. Record it again if it was a mistake.');
    hx_trigger('readingsChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('lab reading delete failed: ' . $exception->getMessage());
    flash('error', !$exception instanceof PDOException && $exception instanceof RuntimeException ? $exception->getMessage() : (db_error_message($exception) ?? 'The reading could not be deleted.'));
}
hx_location('/lab/');
