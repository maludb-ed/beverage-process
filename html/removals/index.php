<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/removals/queries.php';

$user = require_login();
$pdo = db();
$query = list_params('-removed_at');
$filters = [
    'destination_kind' => in_options(request_string('destination_kind', 30), REMOVAL_DESTINATIONS) ? request_string('destination_kind', 30) : '',
    'customer_id' => request_integer('customer_id'),
    'status' => in_options(request_string('status', 20), REMOVAL_STATUSES) ? request_string('status', 20) : '',
    'date_from' => removals_valid_date(request_string('date_from', 10)),
    'date_to' => removals_valid_date(request_string('date_to', 10)),
];
$result = find_removals($pdo, $query['q'], $filters, $query['sort'], $query['page']);
$data = ['result' => $result, 'query' => ['q' => $query['q'], 'sort' => $query['sort']] + $filters, 'customers' => customers_options($pdo, $filters['customer_id']),
    'canEdit' => user_can($user, 'compliance')];

if (is_results_request('removals-list-results')) {
    header('Vary: HX-Request');
    echo view('removals/partials/table.php', $data);
    exit;
}
log_screen_entered('removals-list');
render_screen('Removals', 'removals-list', view('removals/page.php', $data));

