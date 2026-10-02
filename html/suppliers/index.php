<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/suppliers/queries.php';

$user = require_login();
$query = list_params('name');
$result = find_suppliers(db(), $query['q'], $query['sort'], $query['page']);
$data = ['result' => $result, 'query' => ['q' => $query['q'], 'sort' => $query['sort']], 'canEdit' => user_can($user, 'receiving')];

if (is_results_request('suppliers-list-results')) {
    header('Vary: HX-Request');
    echo view('suppliers/partials/table.php', $data);
    exit;
}
log_screen_entered('suppliers-list');
render_screen('Vendors', 'suppliers-list', view('suppliers/page.php', $data));
