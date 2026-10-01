<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/reports/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/reports/params.php';

$user = require_login();
$pdo = db();
$premisesId = reports_premises_param(premises_options($pdo));
$groupBy = request_string('group_by', 20);
$groupBy = array_key_exists($groupBy, REPORT_GROUP_BY) ? $groupBy : 'item_class';
$asOf = min(reports_date_param('as_of', today()), today());
$result = find_valuation_rows($pdo, $asOf, $premisesId, $groupBy, request_string('sort', 40) ?: '-value');
$total = array_sum(array_map(static fn(array $r): float => (float) $r['value'], $result));
$rows = [];
foreach ($result as $r) {
    $rows[] = [$r['group_label'], reports_csv_num(to_display($r['qty_on_hand'], $r['base_unit_code'])), display_unit($r['base_unit_code']), reports_csv_num($r['value'], 2),
        $total != 0.0 ? reports_csv_num(100 * (float) $r['value'] / $total, 2) : ''];
}
$headers = [REPORT_GROUP_BY[$groupBy], 'Quantity on hand', 'Unit', 'Value ($)', 'Share (%)'];
$filters = ['as_of' => $asOf, 'group_by' => $groupBy, 'premises_id' => $premisesId];
$name = 'valuation';

log_activity($pdo, 'report_exported', 'report', null, $name, null, null, ['report' => $name, 'filters' => $filters, 'rows' => count($rows)], 'report-' . $name);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $name . '-' . today() . '.csv"');
header('Cache-Control: no-store');
echo view('reports/csv.php', ['headers' => $headers, 'rows' => $rows]);
