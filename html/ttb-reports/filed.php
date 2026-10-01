<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/ttb-reports/queries.php';

require_post();
verify_csrf();
$user = require_role('compliance');
$pdo = db();
$id = request_integer('id') ?? not_found('That report does not exist.');
$tz = new DateTimeZone((string) config('app.timezone'));
$raw = request_string('filed_at', 20);
$filedAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $raw, $tz) ?: DateTimeImmutable::createFromFormat('!Y-m-d', $raw, $tz);
if ($filedAt === false || $filedAt > new DateTimeImmutable('now', $tz)) {
    $report = find_period_report($pdo, $id) ?? not_found('That report does not exist.');
    http_response_code(422);
    render_screen($report['number'], 'ttb-report-view', view('ttb-reports/partials/view.php', [
        'report' => $report, 'lines' => find_period_report_lines($pdo, $id), 'map' => ttb_reports_line_map($pdo, (string) $report['form_code']), 'user' => $user,
        'filedError' => 'Enter when the report was filed (not in the future).',
    ]), 'period_report', $id);
    exit;
}
try {
    $pdo->beginTransaction();
    $report = find_period_report($pdo, $id, true) ?? not_found('That report does not exist.');
    $after = mark_period_report_filed($pdo, $id, $filedAt->format(DATE_ATOM), (int) $user['id']);
    log_activity($pdo, 'ttb_report_marked_filed', 'period_report', $id, $report['number'], ['status' => $report['status'], 'filed_at' => $report['filed_at']], $after, [], 'ttb-report-view');
    $pdo->commit();
    flash('success', 'Report ' . $report['number'] . ' marked filed on ' . format_datetime($after['filed_at']) . '.');
    hx_trigger('ttbReportsChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('ttb report filed failed: ' . $exception->getMessage());
    flash('error', !$exception instanceof PDOException && $exception instanceof RuntimeException ? $exception->getMessage() : 'The report could not be marked filed.');
}
hx_location('/ttb-reports/' . $id);
