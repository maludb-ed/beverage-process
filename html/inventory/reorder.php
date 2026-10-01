<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/inventory/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/lots/queries.php';

$user = require_login();
$query = list_params('name');
$classes = lot_item_class_options();
$itemClass = request_string('item_class', 30);
$itemClass = in_options($itemClass, $classes) ? $itemClass : '';
$result = find_reorder_items(db(), $itemClass ?: null, $query['sort'], $query['page']);
$data = ['result' => $result, 'classes' => $classes, 'query' => ['sort' => $query['sort'], 'item_class' => $itemClass], 'canEdit' => user_can($user, 'receiving')];

if (is_results_request('reorder-list-results')) {
    header('Vary: HX-Request');
    echo view('inventory/partials/reorder-table.php', $data);
    exit;
}
log_screen_entered('reorder-list');
render_screen('Reorder', 'reorder-list', view('inventory/reorder-page.php', $data));
