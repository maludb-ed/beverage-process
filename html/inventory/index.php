<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/inventory/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/lots/queries.php';

$user = require_login();
$pdo = db();
$query = list_params('item_name');
$classes = lot_item_class_options();
$locations = inventory_location_options(inventory_locations($pdo));
$itemClass = request_string('item_class', 30);
$itemClass = in_options($itemClass, $classes) ? $itemClass : '';
$locationId = request_integer('location_id');
$locationId = $locationId !== null && isset($locations[$locationId]) ? $locationId : null;
$result = find_inventory_balances($pdo, $query['q'], ['item_class' => $itemClass, 'location_id' => $locationId], $query['sort'], $query['page']);
$data = [
    'result' => $result, 'classes' => $classes, 'locations' => $locations,
    'query' => ['q' => $query['q'], 'sort' => $query['sort'], 'item_class' => $itemClass, 'location_id' => $locationId],
    'canEdit' => user_can($user, 'receiving'),
];

if (is_results_request('inventory-list-results')) {
    header('Vary: HX-Request');
    echo view('inventory/partials/table.php', $data);
    exit;
}
log_screen_entered('inventory-list');
render_screen('Inventory', 'inventory-list', view('inventory/page.php', $data));
