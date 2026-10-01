<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/reports/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/reports/params.php';

$user = require_login();
$pdo = db();
$products = find_report_products($pdo);
$productId = reports_option_param('product', $products);
$status = request_string('status', 20);
$status = array_key_exists($status, REPORT_BATCH_STATUSES) ? $status : '';
$dateFrom = reports_date_param('date_from', reports_default_date_from());
$dateTo = reports_date_param('date_to');
$statuses = $status === '' ? [] : [$status];
$result = find_batch_cost_rows($pdo, $productId, $statuses, $dateFrom, $dateTo, request_string('sort', 40) ?: '-started_at', 1, 1000000);
$packageCosts = find_batch_package_costs($pdo, array_map(static fn(array $r): int => (int) $r['batch_id'], $result['rows']));
$vol = display_unit('L');
$rows = [];
foreach ($result['rows'] as $r) {
    $pkg = $packageCosts[(int) $r['batch_id']] ?? ['per_keg' => null, 'per_case' => null];
    $perGal = $r['liquid_cost_per_l'] === null ? null : (float) $r['liquid_cost_per_l'] * LITERS_PER_GALLON;
    $rows[] = [$r['number'], $r['product_name'], $r['status'], substr((string) $r['started_at'], 0, 10), reports_csv_num(to_display($r['starting_volume_l'], 'L')),
        reports_csv_num($r['material_cost'], 2), reports_csv_num($r['packaging_cost'], 2), reports_csv_num($r['overhead_cost'], 2), reports_csv_num($r['total_cost'], 2),
        reports_csv_num($perGal, 4), reports_csv_num($r['standard_cost_total'], 2), reports_csv_num($r['variance_to_standard'], 2), reports_csv_num($pkg['per_keg'], 2), reports_csv_num($pkg['per_case'], 2)];
}
$headers = ['Batch', 'Product', 'Status', 'Started', "Starting volume ($vol)", 'Material cost ($)', 'Packaging cost ($)', 'Overhead cost ($)', 'Total cost ($)', "Cost per $vol ($)", 'Standard cost ($)', 'Variance to standard ($)', 'Per keg ($)', 'Per case ($)'];
$filters = ['product_id' => $productId, 'status' => $status, 'date_from' => $dateFrom, 'date_to' => $dateTo];
$name = 'batch-costs';

log_activity($pdo, 'report_exported', 'report', null, $name, null, null, ['report' => $name, 'filters' => $filters, 'rows' => count($rows)], 'report-' . $name);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $name . '-' . today() . '.csv"');
header('Cache-Control: no-store');
echo view('reports/csv.php', ['headers' => $headers, 'rows' => $rows]);
