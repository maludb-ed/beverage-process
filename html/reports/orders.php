<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/reports/orders.php';
require_once dirname(__DIR__, 2) . '/app/features/reports/params.php';

$user = require_login();
$pdo = db();
$customers = report_order_customers($pdo);
$groupBy = request_string('group_by', 20);
$groupBy = isset(REPORT_ORDER_GROUP_BY[$groupBy]) ? $groupBy : 'customer';
$dateFrom = reports_date_param('date_from', reports_default_date_from());
$dateTo = reports_date_param('date_to', (new DateTimeImmutable('today'))->modify('+3 months')->format('Y-m-d'));
$customerId = reports_option_param('customer_id', $customers);
$query = ['group_by' => $groupBy, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'customer_id' => $customerId ?? '', 'sort' => request_string('sort', 40) ?: '-units'];
$rows = find_order_history_rows($pdo, $groupBy, $dateFrom, $dateTo, $customerId, $query['sort']);
$csvUrl = '/reports/orders/csv?' . http_build_query(array_diff_key($query, ['sort' => 1]));
$data = ['rows' => $rows, 'query' => $query, 'canPrice' => user_can($user, 'sales')];
if (is_results_request('report-orders-results')) {
    header('Vary: HX-Request');
    echo view('reports/partials/orders-table.php', $data), view('reports/partials/export-button.php', ['screen' => 'report-orders', 'url' => $csvUrl, 'oob' => true]);
    exit;
}
log_screen_entered('report-orders');
render_screen('Order history', 'report-orders', view('reports/page.php', [
    'title' => 'Order history', 'screen' => 'report-orders',
    'filtersHtml' => view('reports/partials/orders-filters.php', ['query' => $query, 'customers' => $customers]),
    'exportHtml' => view('reports/partials/export-button.php', ['screen' => 'report-orders', 'url' => $csvUrl]),
    'resultsHtml' => view('reports/partials/orders-table.php', $data),
]));
