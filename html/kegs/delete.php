<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/kegs/queries.php';

require_post();
verify_csrf();
$user = require_role('production');
$pdo = db();
$id = request_integer('id') ?? not_found('That keg does not exist.');
$keg = find_keg($pdo, $id) ?? not_found('That keg does not exist.');
try {
    $pdo->beginTransaction();
    if (!delete_keg($pdo, $id)) {
        throw new RuntimeException('Only a keg that was never filled and has no movements can be deleted.');
    }
    log_activity($pdo, 'keg_deleted', 'keg', $id, $keg['serial'], ['serial' => $keg['serial'], 'state' => $keg['state']], null, [], 'keg-view');
    $pdo->commit();
    flash('success', 'Keg ' . $keg['serial'] . ' deleted.');
    hx_trigger('kegsChanged');
    hx_location('/kegs/');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('keg delete failed: ' . $exception->getMessage());
    flash('error', $exception instanceof PDOException ? (db_error_message($exception) ?? 'The keg could not be deleted.') : $exception->getMessage());
}
hx_location('/kegs/' . $id);
