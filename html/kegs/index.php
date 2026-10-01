<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/kegs/queries.php';

$user = require_login();
$pdo = db();
$query = list_params('serial');
$state = request_string('state', 20);
$state = in_options($state, KEG_STATES) ? $state : '';
$customers = keg_customer_options($pdo);
$customerId = request_integer('customer_id');
$customerId = $customerId !== null && isset($customers[$customerId]) ? $customerId : null;
$days = request_integer('older_than_days');
$days = in_array($days, [30, 60, 90], true) ? $days : null;
$result = find_kegs($pdo, $query['q'], ['state' => $state, 'customer_id' => $customerId, 'older_than_days' => $days], $query['sort'], $query['page']);
$data = [
    'result' => $result, 'counts' => find_keg_state_counts($pdo), 'customers' => $customers, 'canEdit' => user_can($user, 'production'),
    'query' => ['q' => $query['q'], 'sort' => $query['sort'], 'state' => $state, 'customer_id' => $customerId, 'older_than_days' => $days],
];

if (is_results_request('kegs-list-results')) {
    header('Vary: HX-Request');
    echo view('kegs/partials/table.php', $data);
    exit;
}
log_screen_entered('kegs-list');
render_screen('Kegs', 'kegs-list', view('kegs/page.php', $data));
