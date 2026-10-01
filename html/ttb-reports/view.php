<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/ttb-reports/queries.php';

$user = require_login();
$pdo = db();
$id = request_integer('id') ?? not_found('That report does not exist.');
$report = find_period_report($pdo, $id) ?? not_found('That report does not exist.');
log_screen_entered('ttb-report-view', 'period_report', $id, $report['number']);
render_screen($report['number'], 'ttb-report-view', view('ttb-reports/partials/view.php', [
    'report' => $report, 'lines' => find_period_report_lines($pdo, $id), 'map' => ttb_reports_line_map($pdo, (string) $report['form_code']), 'user' => $user, 'filedError' => null,
]), 'period_report', $id);
