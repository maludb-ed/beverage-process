<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/ttb-reports/queries.php';

// ttb_report_generate: insert the report for the premises and period and generate its lines in one transaction.
// Accepts period_kind + period from the form, or period_start + period_end (Y-m-d) from the command bar.
require_post();
verify_csrf();
$user = require_role('compliance');
$pdo = db();
$premises = ttb_reports_premises_options($pdo);
$report = ['premises_id' => request_integer('premises_id'), 'period_kind' => request_string('period_kind', 10), 'period' => request_string('period', 10)];
$errors = [];
if ($report['premises_id'] === null || !isset($premises[$report['premises_id']])) { $errors['premises_id'] = 'Choose a premises that files form 5120.17.'; }
$startRaw = request_string('period_start', 10);
$endRaw = request_string('period_end', 10);
if ($startRaw !== '' || $endRaw !== '') {
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startRaw);
    $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endRaw);
    $bounds = $start && $end && $start->format('Y-m-d') === $startRaw && $end->format('Y-m-d') === $endRaw && $end >= $start ? [$startRaw, $endRaw] : null;
    if ($bounds === null) { $errors['period'] = 'Enter a period start and end (end on or after start).'; }
} else {
    if (!in_options($report['period_kind'], PERIOD_KINDS)) { $errors['period_kind'] = 'Choose month, quarter or year.'; }
    $bounds = ttb_reports_period_bounds($report['period_kind'], $report['period']);
    if ($bounds === null) { $errors['period'] = 'Choose the period to report.'; }
}
if ($errors === [] && $bounds[0] > today()) { $errors['period'] = 'That period has not started yet.'; }

if ($errors === []) {
    $existing = find_existing_period_report($pdo, $report['premises_id'], '5120.17', $bounds[0], $bounds[1]);
    if ($existing !== null) {
        flash('info', 'Report ' . $existing['number'] . ' already covers that period' . ($existing['status'] === 'draft' ? '; regenerate it to refresh the numbers.' : '.'));
        hx_location('/ttb-reports/' . (int) $existing['id']);
    }
    try {
        $pdo->beginTransaction();
        $inserted = insert_period_report($pdo, $report['premises_id'], '5120.17', $bounds[0], $bounds[1], (int) $user['id']);
        $result = generate_period_report($pdo, (int) $inserted['id'], (int) $user['id']);
        log_activity($pdo, 'ttb_report_generated', 'period_report', (int) $inserted['id'], $inserted['number'], null, $result['totals'],
            ['premises_id' => $report['premises_id'], 'period_start' => $bounds[0], 'period_end' => $bounds[1]], 'ttb-report-add');
        $pdo->commit();
        flash('success', 'Report ' . $inserted['number'] . ' generated for ' . format_date($bounds[0]) . ' to ' . format_date($bounds[1]) . '.');
        hx_trigger('ttbReportsChanged');
        hx_location('/ttb-reports/' . (int) $inserted['id']);
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('ttb report generate failed: ' . $exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (is_unique_violation($exception) ? 'A report for that period already exists.' : (db_error_message($exception) ?? 'The report could not be generated.')) : $exception->getMessage();
    }
}
if (!in_options($report['period_kind'], PERIOD_KINDS)) { $report['period_kind'] = 'month'; }
http_response_code(422);
render_screen('Generate TTB report', 'ttb-report-add', view('ttb-reports/partials/form.php', ['report' => $report, 'premises' => $premises, 'errors' => $errors]), 'period_report');
