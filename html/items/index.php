<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/items/queries.php';

$user = require_login();
$query = list_params('code');
$pdo = db();
$classes = item_class_options($pdo, null, false);
$query['item_class'] = in_options(request_string('item_class'), $classes) ? request_string('item_class') : '';
$result = find_items($pdo, $query['q'], $query['sort'], $query['page'], $query['item_class'] ?: null, false);
$data = ['result' => $result, 'query' => $query, 'classes' => $classes, 'canEdit' => user_can($user, 'receiving')];

if (is_results_request('items-list-results')) {
    header('Vary: HX-Request');
    echo view('items/partials/table.php', $data);
    exit;
}
log_screen_entered('items-list');
render_screen('Items', 'items-list', view('items/page.php', $data));
