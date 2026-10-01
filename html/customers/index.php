<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/customers/queries.php';

$user = require_login();
$query = list_params('name');
$result = find_customers(db(), $query['q'], $query['sort'], $query['page']);
$data = ['result' => $result, 'query' => ['q' => $query['q'], 'sort' => $query['sort']], 'canEdit' => user_can($user, 'compliance')];

if (is_results_request('customers-list-results')) {
    header('Vary: HX-Request');
    echo view('customers/partials/table.php', $data);
    exit;
}
log_screen_entered('customers-list');
render_screen('Customers', 'customers-list', view('customers/page.php', $data));
