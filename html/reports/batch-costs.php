<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/reports/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/reports/params.php';

$user = require_login();
$pdo = db();
$products = find_report_products($pdo);
$productId = reports_option_param('product', $products);
$status = request_string('status', 20);
$status = array_key_exists($status, REPORT_BATCH_STATUSES) ? $status : '';
$query = [
    'product' => $productId ?? '', 'status' => $status,
    'date_from' => reports_date_param('date_from', reports_default_date_from()), 'date_to' => reports_date_param('date_to') ?? '',
    'sort' => request_string('sort', 40) ?: '-started_at',
];
$page = max(1, request_integer('page') ?? 1);
$statuses = $status === '' ? [] : [$status];
$result = find_batch_cost_rows($pdo, $productId, $statuses, $query['date_from'], $query['date_to'] ?: null, $query['sort'], $page, 50);
$batchIds = array_map(static fn(array $r): int => (int) $r['batch_id'], $result['rows']);
$csvUrl = '/reports/batch-costs/csv?' . http_build_query(array_diff_key($query, ['sort' => 1]));
$table = view('reports/partials/batch-costs-table.php', [
    'result' => $result, 'query' => $query, 'packageCosts' => find_batch_package_costs($pdo, $batchIds),
    'totals' => find_batch_cost_totals($pdo, $productId, $statuses, $query['date_from'], $query['date_to'] ?: null), 'csvUrl' => $csvUrl,
]);

if (is_results_request('report-batch-costs-results')) {
    header('Vary: HX-Request');
    echo $table, view('reports/partials/export-button.php', ['screen' => 'report-batch-costs', 'url' => $csvUrl, 'oob' => true]);
    exit;
}
log_screen_entered('report-batch-costs');
render_screen('Batch costs', 'report-batch-costs', view('reports/page.php', [
    'title' => 'Batch costs', 'screen' => 'report-batch-costs',
    'filtersHtml' => view('reports/partials/batch-costs-filters.php', ['query' => $query, 'products' => $products]),
    'exportHtml' => view('reports/partials/export-button.php', ['screen' => 'report-batch-costs', 'url' => $csvUrl]),
    'resultsHtml' => $table,
]));
