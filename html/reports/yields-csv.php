<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/reports/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/reports/params.php';

$user = require_login();
$pdo = db();
$products = find_report_products($pdo);
$stages = find_report_stages($pdo);
$productId = reports_option_param('product', $products);
$stage = request_string('stage', 30);
$stage = array_key_exists($stage, $stages) ? $stage : '';
$batch = request_string('batch', 50);
$dateFrom = reports_date_param('date_from', reports_default_date_from());
$dateTo = reports_date_param('date_to');
$result = find_yield_rows($pdo, $productId, $batch, $dateFrom, $dateTo, $stage ?: null, request_string('sort', 40) ?: 'entered_at', 1, 1000000);
$vol = display_unit('L');
$rows = [];
foreach ($result['rows'] as $r) {
    $variance = $r['actual_loss_pct'] !== null && $r['expected_loss_pct'] !== null ? (float) $r['actual_loss_pct'] - (float) $r['expected_loss_pct'] : null;
    $rows[] = [$r['number'], $r['product_name'], $r['stage_name'], substr((string) $r['entered_at'], 0, 10), reports_csv_num(to_display($r['volume_in_l'], 'L')), reports_csv_num(to_display($r['volume_out_l'], 'L')),
        reports_csv_num($r['actual_loss_pct'], 2), reports_csv_num($r['expected_loss_pct'], 2), reports_csv_num($variance, 2), reports_csv_num(to_display($r['recorded_loss_l'], 'L'))];
}
$headers = ['Batch', 'Product', 'Stage', 'Entered', "Volume in ($vol)", "Volume out ($vol)", 'Actual loss (%)', 'Expected loss (%)', 'Variance (points)', "Recorded loss ($vol)"];
$filters = ['product_id' => $productId, 'batch' => $batch, 'stage' => $stage, 'date_from' => $dateFrom, 'date_to' => $dateTo];
$name = 'yields';

log_activity($pdo, 'report_exported', 'report', null, $name, null, null, ['report' => $name, 'filters' => $filters, 'rows' => count($rows)], 'report-' . $name);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $name . '-' . today() . '.csv"');
header('Cache-Control: no-store');
echo view('reports/csv.php', ['headers' => $headers, 'rows' => $rows]);
