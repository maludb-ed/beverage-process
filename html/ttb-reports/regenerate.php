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
    $before = array_diff_key($report['totals'], ['previous_lines' => true]);
    $result = generate_period_report($pdo, $id, (int) $user['id']);
    log_activity($pdo, 'ttb_report_regenerated', 'period_report', $id, $report['number'], $before, $result['totals'], ['previous_generated_at' => $report['generated_at']], 'ttb-report-view');
    $pdo->commit();
    flash('success', 'Report ' . $report['number'] . ' regenerated from the current records.');
    hx_trigger('ttbReportsChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('ttb report regenerate failed: ' . $exception->getMessage());
    flash('error', !$exception instanceof PDOException && $exception instanceof RuntimeException ? $exception->getMessage() : (db_error_message($exception) ?? 'The report could not be regenerated.'));
}
hx_location('/ttb-reports/' . $id);
