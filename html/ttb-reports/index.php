<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/ttb-reports/queries.php';

$user = require_login();
$query = list_params('-period_start');
$result = find_period_reports(db(), $query['q'], $query['sort'], $query['page']);
$data = ['result' => $result, 'query' => ['q' => $query['q'], 'sort' => $query['sort']], 'canEdit' => user_can($user, 'compliance')];

if (is_results_request('ttb-reports-list-results')) {
    header('Vary: HX-Request');
    echo view('ttb-reports/partials/table.php', $data);
    exit;
}
log_screen_entered('ttb-reports-list');
render_screen('TTB reports', 'ttb-reports-list', view('ttb-reports/page.php', $data));
