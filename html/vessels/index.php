<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/vessels/queries.php';

$user = require_login();
$query = list_params('name');
$result = find_vessels(db(), $query['q'], $query['sort'], $query['page']);
$data = ['result' => $result, 'query' => ['q' => $query['q'], 'sort' => $query['sort']], 'canEdit' => user_can($user, 'production')];

if (is_results_request('vessels-list-results')) {
    header('Vary: HX-Request');
    echo view('vessels/partials/table.php', $data);
    exit;
}
log_screen_entered('vessels-list');
render_screen('Vessels', 'vessels-list', view('vessels/page.php', $data));
