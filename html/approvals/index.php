<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/approvals/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/products/queries.php';

$user = require_login();
$pdo = db();
$query = list_params('product_name');
$kind = request_string('kind', 10);
$status = request_string('status', 20);
$productId = request_integer('product_id');
$filters = [
    'kind' => in_options($kind, APPROVAL_KINDS) ? $kind : '',
    'status' => in_options($status, APPROVAL_STATUSES) ? $status : '',
    'product_id' => $productId ?? '',
];
$products = products_options($pdo, true);
$data = [
    'result' => find_approvals($pdo, $query['q'], $filters, $query['sort'], $query['page']),
    'query' => ['q' => $query['q'], 'sort' => $query['sort']] + $filters,
    'canEdit' => user_can($user, 'compliance'), 'products' => $products,
];

if (is_results_request('approvals-list-results')) {
    header('Vary: HX-Request');
    echo view('approvals/partials/table.php', $data);
    exit;
}
$data['missing'] = find_products_missing_approvals($pdo);
log_screen_entered('approvals-list');
render_screen('Approvals', 'approvals-list', view('approvals/page.php', $data));
