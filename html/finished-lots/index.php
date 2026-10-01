<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/finished-lots/queries.php';

$user = require_login();
$pdo = db();
$query = list_params('-packaged_on');
$products = finished_lot_product_options($pdo);
$locations = finished_lot_location_options($pdo);
$productId = request_integer('product_id');
$productId = $productId !== null && isset($products[$productId]) ? $productId : null;
$kind = request_string('package_kind', 10);
$kind = in_options($kind, FINISHED_LOT_PACKAGE_KINDS) ? $kind : '';
$locationId = request_integer('location_id');
$locationId = $locationId !== null && isset($locations[$locationId]) ? $locationId : null;
$result = find_finished_lots($pdo, $query['q'], ['product_id' => $productId, 'package_kind' => $kind, 'location_id' => $locationId], $query['sort'], $query['page']);
$data = [
    'result' => $result, 'products' => $products, 'locations' => $locations,
    'query' => ['q' => $query['q'], 'sort' => $query['sort'], 'product_id' => $productId, 'package_kind' => $kind, 'location_id' => $locationId],
];

if (is_results_request('finished-lots-list-results')) {
    header('Vary: HX-Request');
    echo view('finished-lots/partials/table.php', $data);
    exit;
}
log_screen_entered('finished-lots-list');
render_screen('Finished goods', 'finished-lots-list', view('finished-lots/page.php', $data));
