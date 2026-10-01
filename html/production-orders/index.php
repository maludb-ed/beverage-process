<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/production-orders/queries.php';

$user = require_login();
$query = list_params('-planned_pitch_on');
$status = request_string('status', 20);
$status = $status === 'all' || in_options($status, PRODUCTION_ORDER_STATUSES) ? $status : '';
$filter = $status === '' ? PRODUCTION_ORDER_DEFAULT_STATUSES : ($status === 'all' ? [] : [$status]);
$result = find_production_orders(db(), $query['q'], $filter, $query['sort'], $query['page']);
$data = ['result' => $result, 'query' => ['q' => $query['q'], 'sort' => $query['sort'], 'status' => $status], 'canEdit' => user_can($user, 'production')];

if (is_results_request('production-orders-list-results')) {
    header('Vary: HX-Request');
    echo view('production-orders/partials/table.php', $data);
    exit;
}
log_screen_entered('production-orders-list');
render_screen('Production orders', 'production-orders-list', view('production-orders/page.php', $data));
