<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/receipts/queries.php';

// Projected receipts: a month calendar of open purchase order lines by expected date (Pattern B).
$user = require_login();
$month = request_string('month', 7);
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    $month = substr(today(), 0, 7);
}
$first = new DateTimeImmutable($month . '-01');
$from = $first->modify('monday this week')->format('Y-m-d');
$to = $first->modify('last day of this month')->modify('sunday this week')->format('Y-m-d');

$data = find_projected_receipts(db(), $from, $to);
$data = ['byDate' => $data['by_date'], 'overdue' => $data['overdue'], 'undated' => $data['undated'], 'month' => $month, 'today' => today()];
if (is_results_request('receipts-projected-grid')) {
    header('Vary: HX-Request');
    echo view('receipts/partials/projected.php', $data);
    exit;
}
// log_screen_entered() cannot carry details, so the screen_entered row is written directly with the month.
$GLOBALS['__current_screen'] = 'receipts-projected';
try { log_activity(db(), 'screen_entered', null, null, null, null, null, ['month' => $month], 'receipts-projected'); } catch (Throwable $e) { error_log('screen_entered log failed: ' . $e->getMessage()); }
render_screen('Projected receipts', 'receipts-projected', view('receipts/projected.php', $data));
