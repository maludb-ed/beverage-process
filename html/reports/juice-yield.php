<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/reports/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/reports/params.php';

$user = require_login();
$pdo = db();
$currentYear = (int) date('Y');
$seasons = array_values(array_unique(array_merge([$currentYear], find_juice_yield_seasons($pdo))));
rsort($seasons);
$year = request_integer('season_year');
$year = $year !== null && in_array($year, $seasons, true) ? $year : $currentYear;
$varieties = find_juice_yield_varieties($pdo);
$variety = request_string('variety', 100);
$variety = in_array($variety, $varieties, true) ? $variety : '';
$premises = premises_options($pdo);
$premisesId = reports_premises_param($premises);
$query = ['season_year' => $year, 'variety' => $variety, 'premises_id' => $premisesId ?? '', 'sort' => request_string('sort', 40) ?: 'run_on'];
$page = max(1, request_integer('page') ?? 1);
$csvUrl = '/reports/juice-yield/csv?' . http_build_query(array_diff_key($query, ['sort' => 1]));
$result = find_juice_yield_rows($pdo, $year, $variety ?: null, $premisesId, $query['sort'], $page, 100);

if (is_results_request('report-juice-yield-detail-results')) {
    header('Vary: HX-Request');
    echo view('reports/partials/juice-yield-table.php', ['result' => $result, 'query' => $query]);
    exit;
}
$results = view('reports/partials/juice-yield-results.php', [
    'summary' => find_juice_yield_summary($pdo, $year, $variety ?: null, $premisesId), 'result' => $result, 'query' => $query,
]);
if (is_results_request('report-juice-yield-results')) {
    header('Vary: HX-Request');
    echo $results, view('reports/partials/export-button.php', ['screen' => 'report-juice-yield', 'url' => $csvUrl, 'oob' => true]);
    exit;
}
log_screen_entered('report-juice-yield');
render_screen('Juice yield', 'report-juice-yield', view('reports/page.php', [
    'title' => 'Juice yield', 'screen' => 'report-juice-yield',
    'filtersHtml' => view('reports/partials/juice-yield-filters.php', ['query' => $query, 'seasons' => $seasons, 'varieties' => $varieties, 'premises' => $premises]),
    'exportHtml' => view('reports/partials/export-button.php', ['screen' => 'report-juice-yield', 'url' => $csvUrl]),
    'resultsHtml' => $results,
]));
