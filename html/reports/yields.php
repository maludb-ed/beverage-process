<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/reports/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/reports/params.php';

$user = require_login();
$pdo = db();
$products = find_report_products($pdo);
$stages = find_report_stages($pdo);
$productId = reports_option_param('product', $products);
$stage = request_string('stage', 30);
$stage = array_key_exists($stage, $stages) ? $stage : '';
$query = [
    'product' => $productId ?? '', 'batch' => request_string('batch', 50), 'stage' => $stage,
    'date_from' => reports_date_param('date_from', reports_default_date_from()), 'date_to' => reports_date_param('date_to') ?? '',
    'sort' => request_string('sort', 40) ?: 'entered_at',
];
$page = max(1, request_integer('page') ?? 1);
$result = find_yield_rows($pdo, $productId, $query['batch'], $query['date_from'], $query['date_to'] ?: null, $stage ?: null, $query['sort'], $page, 100);
$totals = find_batch_yield_totals($pdo, array_values(array_unique(array_map(static fn(array $r): int => (int) $r['batch_id'], $result['rows']))));
$filters = array_diff_key($query, ['sort' => 1]);
$csvUrl = '/reports/yields/csv?' . http_build_query($filters);
$table = view('reports/partials/yields-table.php', ['result' => $result, 'query' => $query]  + ['totals' => $totals, 'csvUrl' => $csvUrl]);

if (is_results_request('report-yields-results')) {
    header('Vary: HX-Request');
    echo $table, view('reports/partials/export-button.php', ['screen' => 'report-yields', 'url' => $csvUrl, 'oob' => true]);
    exit;
}
log_screen_entered('report-yields');
render_screen('Yield report', 'report-yields', view('reports/page.php', [
    'title' => 'Yield report', 'screen' => 'report-yields',
    'filtersHtml' => view('reports/partials/yields-filters.php', ['query' => $query, 'products' => $products, 'stages' => $stages]),
    'exportHtml' => view('reports/partials/export-button.php', ['screen' => 'report-yields', 'url' => $csvUrl]),
    'resultsHtml' => $table,
]));
