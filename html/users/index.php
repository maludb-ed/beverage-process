<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/users/queries.php';

$user = require_role();
$query = list_params('display_name');
$result = find_users(db(), $query['q'], $query['sort'], $query['page']);
$data = ['result' => $result, 'query' => ['q' => $query['q'], 'sort' => $query['sort']], 'currentUserId' => (int) $user['id'], 'errors' => []];

if (is_results_request('users-list-results')) {
    header('Vary: HX-Request');
    echo view('users/partials/table.php', $data);
    exit;
}
log_screen_entered('users-list');
render_screen('Users', 'users-list', (os_enabled() ? os_managed_notice() : '') . view('users/page.php', $data));
