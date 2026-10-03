<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/standard-costs/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/items/queries.php';

$user = require_login();
$pdo = db();
$query = list_params('name');
$classes = item_class_options($pdo, null, false);
$class = request_string('item_class', 30);
$method = request_string('costing_method', 20);
$filters = ['item_class' => in_options($class, $classes) ? $class : '', 'costing_method' => in_options($method, STANDARD_COST_METHODS) ? $method : ''];
$data = ['result' => find_standard_cost_items($pdo, $query['q'], $filters, $query['sort'], $query['page']), 'query' => ['q' => $query['q'], 'sort' => $query['sort']] + $filters, 'classes' => $classes, 'canEdit' => user_can($user)];

if (is_results_request('standard-costs-list-results')) {
    header('Vary: HX-Request');
    echo view('standard-costs/partials/table.php', $data);
    exit;
}
$data['overheadRates'] = find_overhead_rates($pdo);
log_screen_entered('standard-costs-list');
render_screen('Standard costs', 'standard-costs-list', view('standard-costs/page.php', $data));
