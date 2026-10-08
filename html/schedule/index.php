<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/schedule/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';

// The Equipment schedule (Pattern B): a timeline of resources × days (default), or a month grid (?view=month).
// Filters: premises_id, kind (vessel | equipment | a vessel or equipment kind), resource (vessel:12), subject (batch:4);
// the window: from (a date, taken back to its Monday) and weeks (1–12) for the timeline, month (YYYY-MM) for the month.
$user = require_login();
$pdo = db();
$tz = new DateTimeZone((string) config('app.timezone'));
$isDate = static fn(string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && ($dt = DateTimeImmutable::createFromFormat('!Y-m-d', $d)) && $dt->format('Y-m-d') === $d;

$view = request_string('view', 10) === 'month' ? 'month' : 'timeline';
$premises = premises_options($pdo);
$premisesId = request_integer('premises_id');
$premisesId = $premisesId !== null && isset($premises[$premisesId]) ? $premisesId : null;
$kind = preg_replace('/[^a-z_]/', '', request_string('kind', 20));
$resourceKey = request_string('resource', 30);
$resourceKey = reservation_resource_key($resourceKey) !== null ? $resourceKey : null;
$subjectKey = request_string('subject', 40);
$subjectKey = preg_match('/^(production_order|batch|press_run|packaging_run):\d{1,12}$/', $subjectKey) ? $subjectKey : null;

$from = request_string('from', 10);
$from = $isDate($from) ? $from : today();
$monday = (new DateTimeImmutable($from, $tz))->modify('monday this week');
$weeks = request_integer('weeks') ?? 4;
$weeks = isset(SCHEDULE_WEEKS[$weeks]) ? $weeks : 4;
$month = request_string('month', 7);
$month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : substr($from, 0, 7);

if ($view === 'month') {
    $first = new DateTimeImmutable($month . '-01', $tz);
    $gridStart = $first->modify('monday this week');
    $gridEnd = $first->modify('last day of this month')->modify('sunday this week')->modify('+1 day');
} else {
    $gridStart = $monday;
    $gridEnd = $monday->modify('+' . ($weeks * 7) . ' days');
}
$resources = find_schedule_resources($pdo, $premisesId, $kind, $resourceKey);
$bookings = find_schedule_bookings($pdo, $gridStart->format(DATE_ATOM), $gridEnd->format(DATE_ATOM), array_keys($resources), $subjectKey);
if ($subjectKey !== null) {
    $resources = array_filter($resources, static fn($r, $key) => $bookings[$key] !== [], ARRAY_FILTER_USE_BOTH);
}
$query = ['view' => $view === 'month' ? 'month' : null, 'premises_id' => $premisesId, 'kind' => $kind ?: null, 'resource' => $resourceKey, 'subject' => $subjectKey,
    'from' => $monday->format('Y-m-d'), 'weeks' => $weeks, 'month' => $month];
$data = [
    'view' => $view, 'resources' => $resources, 'bookings' => $bookings, 'occupants' => find_schedule_occupants($pdo), 'query' => $query,
    'gridStart' => $gridStart, 'gridEnd' => $gridEnd, 'today' => today(), 'premises' => $premises, 'canEdit' => user_can($user, 'production'),
    'catalog' => reservation_resource_catalog($pdo),
];
if (is_results_request('schedule-results')) {
    header('Vary: HX-Request');
    echo view('schedule/partials/' . $view . '.php', $data);
    exit;
}
// log_screen_entered() cannot carry details, so the screen_entered row is written directly with the window and filters.
$GLOBALS['__current_screen'] = 'equipment-schedule';
try { log_activity(db(), 'screen_entered', null, null, null, null, null, array_filter($query, static fn($v) => $v !== null), 'equipment-schedule'); } catch (Throwable $e) { error_log('screen_entered log failed: ' . $e->getMessage()); }
render_screen('Equipment schedule', 'equipment-schedule', view('schedule/page.php', $data));
