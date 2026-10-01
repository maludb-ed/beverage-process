<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/sensory/queries.php';

$user = require_login();
$query = list_params('-panel_on');
$result = find_sensory_records(db(), $query['q'], $query['sort'], $query['page']);
$data = ['result' => $result, 'user' => $user, 'query' => ['q' => $query['q'], 'sort' => $query['sort']]];

if (is_results_request('sensory-list-results')) {
    header('Vary: HX-Request');
    echo view('sensory/partials/table.php', $data);
    exit;
}
log_screen_entered('sensory-list');
render_screen('Sensory panel', 'sensory-list', view('sensory/page.php', $data));
