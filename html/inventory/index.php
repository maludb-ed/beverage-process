<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/inventory/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/items/queries.php';

// On hand, split into two screens: /inventory/materials and /inventory/finished (Pattern B).
// /inventory/ itself picks the screen from the item_class filter so older links keep working.
$user = require_login();
$pdo = db();
$kind = request_string('kind', 10);
$itemClass = request_string('item_class', 30);
if (!isset(INVENTORY_KINDS[$kind])) {
    $kind = ($itemClass !== '' && (item_class_rows($pdo)[$itemClass]['kind'] ?? 'material') === 'finished') ? 'finished' : 'materials';
}
$screen = INVENTORY_KINDS[$kind]['screen'];
$classes = item_class_options($pdo, $kind === 'finished' ? 'finished' : 'material', false);
$query = list_params('item_name');
$locations = inventory_location_options(inventory_locations($pdo));
$itemClass = in_options($itemClass, $classes) ? $itemClass : '';
$locationId = request_integer('location_id');
$locationId = $locationId !== null && isset($locations[$locationId]) ? $locationId : null;
$result = find_inventory_balances($pdo, $query['q'], ['item_class' => $itemClass, 'item_classes' => array_keys($classes), 'location_id' => $locationId], $query['sort'], $query['page']);
$data = [
    'result' => $result, 'classes' => $classes, 'locations' => $locations, 'kind' => $kind,
    'query' => ['q' => $query['q'], 'sort' => $query['sort'], 'item_class' => $itemClass, 'location_id' => $locationId],
    'canEdit' => user_can($user, 'receiving'),
];

if (is_results_request($screen . '-results')) {
    header('Vary: HX-Request');
    echo view('inventory/partials/table.php', $data);
    exit;
}
log_screen_entered($screen);
render_screen(INVENTORY_KINDS[$kind]['title'], $screen, view('inventory/page.php', $data));
