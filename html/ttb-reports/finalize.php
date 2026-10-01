<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/ttb-reports/queries.php';

require_post();
verify_csrf();
$user = require_role('compliance');
$pdo = db();
$id = request_integer('id') ?? not_found('That report does not exist.');
try {
    $pdo->beginTransaction();
    $report = find_period_report($pdo, $id, true) ?? not_found('That report does not exist.');
    $after = finalize_period_report($pdo, $id, (int) $user['id']);
    log_activity($pdo, 'ttb_report_finalized', 'period_report', $id, $report['number'], ['status' => $report['status']], $after, [], 'ttb-report-view');
    $pdo->commit();
    flash('success', 'Report ' . $report['number'] . ' finalized. File it with TTB, then mark it filed.');
    hx_trigger('ttbReportsChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('ttb report finalize failed: ' . $exception->getMessage());
    flash('error', !$exception instanceof PDOException && $exception instanceof RuntimeException ? $exception->getMessage() : 'The report could not be finalized.');
}
hx_location('/ttb-reports/' . $id);
