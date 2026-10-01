<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/ttb-reports/queries.php';

// ttb-report-add. Prefill ?period=YYYY-MM | YYYY-Qn | YYYY. Changing the period kind re-renders the form (?refresh=1).
$user = require_role('compliance');
$pdo = db();
$premises = ttb_reports_premises_options($pdo);
$premisesId = request_integer('premises_id');
$premisesId = $premisesId !== null && isset($premises[$premisesId]) ? $premisesId : (count($premises) >= 1 ? (int) array_key_first($premises) : null);
$period = request_string('period', 10);
$kind = request_string('period_kind', 10);
if (!in_options($kind, PERIOD_KINDS)) {
    $kind = ttb_reports_period_kind_of($period) ?? PERIOD_KIND_BY_FREQUENCY[$premisesId !== null ? ttb_reports_premises_frequency($pdo, $premisesId) : 'monthly'] ?? 'month';
}
if (ttb_reports_period_bounds($kind, $period) === null) {
    $period = ttb_reports_previous_period($kind, today());
}
$report = ['premises_id' => $premisesId, 'period_kind' => $kind, 'period' => $period];
if (request_string('refresh', 1) !== '1') {
    log_screen_entered('ttb-report-add', 'period_report', null, null);
}
render_screen('Generate TTB report', 'ttb-report-add', view('ttb-reports/partials/form.php', ['report' => $report, 'premises' => $premises, 'errors' => []]), 'period_report');
