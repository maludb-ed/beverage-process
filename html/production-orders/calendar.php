<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/production-orders/queries.php';

$user = require_login();
$valid = static fn(string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && ($dt = DateTimeImmutable::createFromFormat('!Y-m-d', $d)) && $dt->format('Y-m-d') === $d;
$from = request_string('from', 10);
$to = request_string('to', 10);
$from = $valid($from) ? $from : today();
$to = $valid($to) ? $to : (new DateTimeImmutable($from))->modify('+8 weeks')->format('Y-m-d');
if ($to < $from) { $to = $from; }
$maxTo = (new DateTimeImmutable($from))->modify('+26 weeks')->format('Y-m-d');
if ($to > $maxTo) { $to = $maxTo; }

$data = find_calendar_rows(db(), $from, $to) + ['from' => $from, 'to' => $to, 'today' => today()];
if (is_results_request('production-calendar-grid')) {
    header('Vary: HX-Request');
    echo view('production-orders/partials/calendar.php', $data);
    exit;
}
// log_screen_entered() cannot carry details, so the same screen_entered row is written directly with the window.
$GLOBALS['__current_screen'] = 'production-calendar';
try { log_activity(db(), 'screen_entered', null, null, null, null, null, ['from' => $from, 'to' => $to], 'production-calendar'); } catch (Throwable $e) { error_log('screen_entered log failed: ' . $e->getMessage()); }
render_screen('Vessel calendar', 'production-calendar', view('production-orders/calendar.php', $data));
