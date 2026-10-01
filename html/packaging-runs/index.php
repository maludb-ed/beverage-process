<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/packaging-runs/queries.php';

$user = require_login();
$query = list_params('-run_on');
$status = request_string('status', 20);
$status = in_options($status, PACKAGING_RUN_STATUSES) ? $status : '';
$result = find_packaging_runs(db(), $query['q'], $status, $query['sort'], $query['page']);
$data = ['result' => $result, 'query' => ['q' => $query['q'], 'sort' => $query['sort'], 'status' => $status], 'canEdit' => user_can($user, 'production')];

if (is_results_request('packaging-runs-list-results')) {
    header('Vary: HX-Request');
    echo view('packaging-runs/partials/table.php', $data);
    exit;
}
log_screen_entered('packaging-runs-list');
render_screen('Packaging runs', 'packaging-runs-list', view('packaging-runs/page.php', $data));
