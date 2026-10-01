<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/ttb-reports/queries.php';

// Drill-down fragment for one report cell, loaded into the #ttb-report-view-drilldown offcanvas:
// GET /ttb-reports/{id}/line/{line_id}.
$user = require_login();
$pdo = db();
$id = request_integer('id') ?? not_found('That report does not exist.');
$lineId = request_integer('sub_id') ?? not_found('That report line does not exist.');
$sources = find_period_report_line_sources($pdo, $lineId);
if (($sources['line']['report_id'] ?? null) === null || (int) $sources['line']['report_id'] !== $id) {
    not_found('That report line does not exist.');
}
$line = $sources['line'];
try {
    log_activity($pdo, 'ttb_report_line_viewed', 'period_report', $id, $line['report_number'], null, null,
        ['line_code' => $line['section'] . $line['line_code'], 'tax_class' => $line['tax_class']], 'ttb-report-view');
} catch (Throwable $exception) {
    error_log('ttb_report_line_viewed log failed: ' . $exception->getMessage());
}
header('Vary: HX-Request');
echo view('ttb-reports/partials/drilldown.php', ['sources' => $sources, 'line' => $line]);
