<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';

$user = require_login();
$query = list_params('name');
$result = find_premises_list(db(), $query['q'], $query['sort'], $query['page']);
$data = ['result' => $result, 'query' => ['q' => $query['q'], 'sort' => $query['sort']], 'canEdit' => user_can($user)];

if (is_results_request('premises-list-results')) {
    header('Vary: HX-Request');
    echo view('premises/partials/table.php', $data);
    exit;
}
log_screen_entered('premises-list');
render_screen('Premises', 'premises-list', view('premises/page.php', $data));
