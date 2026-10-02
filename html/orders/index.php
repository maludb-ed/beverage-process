<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/queries.php';

$user = require_login();
$query = list_params('requested_on');
// Open orders by default; the blank "All statuses" choice shows every order.
$status = array_key_exists('status', $_GET) ? request_string('status', 20) : 'open';
$status = in_options($status, ORDER_STATUS_FILTERS) ? $status : '';
$result = find_orders(db(), $query['q'], $query['sort'], $query['page'], $status ?: null);
$data = ['result' => $result, 'query' => ['q' => $query['q'], 'sort' => $query['sort'], 'status' => $status],
         'canEdit' => user_can($user, 'sales'), 'canPrice' => user_can($user, 'sales')];

if (is_results_request('orders-list-results')) {
    header('Vary: HX-Request');
    echo view('orders/partials/table.php', $data);
    exit;
}
log_screen_entered('orders-list');
render_screen('Customer orders', 'orders-list', view('orders/page.php', $data));
