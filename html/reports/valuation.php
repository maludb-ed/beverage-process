<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/reports/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/reports/params.php';

$user = require_login();
$pdo = db();
$premises = premises_options($pdo);
$premisesId = reports_premises_param($premises);
$groupBy = request_string('group_by', 20);
$groupBy = array_key_exists($groupBy, REPORT_GROUP_BY) ? $groupBy : 'item_class';
$asOf = min(reports_date_param('as_of', today()), today());
$query = ['as_of' => $asOf, 'group_by' => $groupBy, 'premises_id' => $premisesId ?? '', 'sort' => request_string('sort', 40) ?: '-value'];
$rows = find_valuation_rows($pdo, $asOf, $premisesId, $groupBy, $query['sort']);
$csvUrl = '/reports/valuation/csv?' . http_build_query(array_diff_key($query, ['sort' => 1]));
$data = ['rows' => $rows, 'query' => $query, 'total' => array_sum(array_map(static fn(array $r): float => (float) $r['value'], $rows))];

if (is_results_request('report-valuation-groups-results')) {
    header('Vary: HX-Request');
    echo view('reports/partials/valuation-table.php', $data);
    exit;
}
$results = view('reports/partials/valuation-results.php', $data + ['taxStates' => find_valuation_by_tax_state($pdo, $asOf, $premisesId)]);
if (is_results_request('report-valuation-results')) {
    header('Vary: HX-Request');
    echo $results, view('reports/partials/export-button.php', ['screen' => 'report-valuation', 'url' => $csvUrl, 'oob' => true]);
    exit;
}
log_screen_entered('report-valuation');
render_screen('Inventory valuation', 'report-valuation', view('reports/page.php', [
    'title' => 'Inventory valuation', 'screen' => 'report-valuation',
    'filtersHtml' => view('reports/partials/valuation-filters.php', ['query' => $query, 'premises' => $premises]),
    'exportHtml' => view('reports/partials/export-button.php', ['screen' => 'report-valuation', 'url' => $csvUrl]),
    'resultsHtml' => $results,
]));
