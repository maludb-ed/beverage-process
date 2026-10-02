<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/standing.php';

// /orders/standing: the list. /orders/standing/{n}: one standing order with its next dates.
$user = require_login();
$pdo = db();
$id = request_integer('sub_id');
if ($id === null) {
    $query = list_params('customer');
    $state = array_key_exists('state', $_GET) ? request_string('state', 10) : 'active';
    $state = in_array($state, ['active', 'ended'], true) ? $state : '';
    $result = find_standing_orders($pdo, $query['q'], $query['sort'], $query['page'], $state === '' ? null : $state === 'active');
    $data = ['result' => $result, 'query' => ['q' => $query['q'], 'sort' => $query['sort'], 'state' => $state], 'canEdit' => user_can($user, 'sales')];
    if (is_results_request('standing-orders-list-results')) {
        header('Vary: HX-Request');
        echo view('orders/partials/standing-table.php', $data);
        exit;
    }
    log_screen_entered('standing-orders-list');
    render_screen('Standing orders', 'standing-orders-list', view('orders/standing-page.php', $data));
    exit;
}
$standing = find_standing_order($pdo, $id) ?? not_found('That standing order does not exist.');
log_screen_entered('standing-order-view', 'standing_order', $id, $standing['number']);
render_screen($standing['number'], 'standing-order-view', view('orders/partials/standing-view.php', [
    'standing' => $standing, 'lines' => find_standing_order_lines($pdo, $id), 'occurrences' => find_standing_occurrences($pdo, $id),
    'orders' => find_standing_order_orders($pdo, $id), 'user' => $user, 'canPrice' => user_can($user, 'sales'),
]), 'standing_order', $id);
