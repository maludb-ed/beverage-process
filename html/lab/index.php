<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/lab/queries.php';

$user = require_login();
$pdo = db();
$query = list_params('-taken_at');
$types = find_measurement_types($pdo);
$specResult = request_string('spec_result', 10) === 'fail' ? 'fail' : '';
$measurement = request_string('measurement_type_code', 30);
$measurement = isset($types[$measurement]) ? $measurement : '';
$days = request_string('days', 4);
$days = in_options($days, READING_DAY_OPTIONS) ? $days : '';
$result = find_readings($pdo, $query['q'], ['spec_result' => $specResult, 'measurement_type_code' => $measurement, 'days' => $days === '' ? 30 : (int) $days], $query['sort'], $query['page']);
$data = ['result' => $result, 'user' => $user, 'types' => $types,
    'query' => ['q' => $query['q'], 'sort' => $query['sort'], 'spec_result' => $specResult, 'measurement_type_code' => $measurement, 'days' => $days]];

if (is_results_request('lab-list-results')) {
    header('Vary: HX-Request');
    echo view('lab/partials/table.php', $data);
    exit;
}
log_screen_entered('lab-list');
render_screen('Lab readings', 'lab-list', view('lab/page.php', $data));
