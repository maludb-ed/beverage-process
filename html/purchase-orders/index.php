<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/purchase-orders/queries.php';

$user = require_login();
$query = list_params('-number');
$status = request_string('status', 20);
$status = in_options($status, PO_STATUSES) ? $status : '';
$result = find_purchase_orders(db(), $query['q'], $query['sort'], $query['page'], $status ?: null);
$data = ['result' => $result, 'query' => ['q' => $query['q'], 'sort' => $query['sort'], 'status' => $status], 'canEdit' => user_can($user, 'receiving')];

if (is_results_request('purchase-orders-list-results')) {
    header('Vary: HX-Request');
    echo view('purchase-orders/partials/table.php', $data);
    exit;
}
log_screen_entered('purchase-orders-list');
render_screen('Purchase orders', 'purchase-orders-list', view('purchase-orders/page.php', $data));
