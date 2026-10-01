<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/press-runs/queries.php';

$user = require_login();
$query = list_params('-run_on');
$status = request_string('status', 20);
$status = in_options($status, PRESS_RUN_STATUSES) ? $status : '';
$result = find_press_runs(db(), $query['q'], $query['sort'], $query['page'], $status ?: null);
$data = ['result' => $result, 'query' => ['q' => $query['q'], 'sort' => $query['sort'], 'status' => $status], 'canEdit' => user_can($user, 'production')];

if (is_results_request('press-runs-list-results')) {
    header('Vary: HX-Request');
    echo view('press-runs/partials/table.php', $data);
    exit;
}
log_screen_entered('press-runs-list');
render_screen('Press runs', 'press-runs-list', view('press-runs/page.php', $data));
