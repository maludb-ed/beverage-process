<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/packaging-configs/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/products/queries.php';

$user = require_login();
$pdo = db();
$query = list_params('product_name');
$kind = request_string('package_kind', 10);
$active = request_string('active', 1);
$filters = [
    'product_id' => request_integer('product_id') ?? '',
    'package_kind' => in_options($kind, PACKAGE_KINDS) ? $kind : '',
    'active' => in_array($active, ['0', '1'], true) ? $active : '',
];
$data = [
    'result' => find_packaging_configurations($pdo, $query['q'], $filters, $query['sort'], $query['page']),
    'query' => ['q' => $query['q'], 'sort' => $query['sort']] + $filters,
    'canEdit' => user_can($user, 'production'), 'products' => products_options($pdo, true),
];

if (is_results_request('packaging-configs-list-results')) {
    header('Vary: HX-Request');
    echo view('packaging-configs/partials/table.php', $data);
    exit;
}
log_screen_entered('packaging-configs-list');
render_screen('Packaging configurations', 'packaging-configs-list', view('packaging-configs/page.php', $data));
