<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/products/queries.php';

$user = require_login();
$query = list_params('name');
$status = request_string('status', 20);
$status = in_options($status, PRODUCT_STATUSES) ? $status : '';
$beverage = request_string('beverage_type', 20);
$beverage = in_options($beverage, PRODUCT_BEVERAGES) ? $beverage : '';
$result = find_products(db(), $query['q'], $status ?: null, $query['sort'], $query['page'], $beverage ?: null);
$data = ['result' => $result, 'query' => ['q' => $query['q'], 'sort' => $query['sort'], 'status' => $status, 'beverage_type' => $beverage], 'canEdit' => user_can($user, 'production')];

if (is_results_request('products-list-results')) {
    header('Vary: HX-Request');
    echo view('products/partials/table.php', $data);
    exit;
}
log_screen_entered('products-list');
render_screen('Products', 'products-list', view('products/page.php', $data));
