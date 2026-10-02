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
$canPrice = user_can($user, 'sales');
$rows = [];
foreach (find_order_history_rows($pdo, $groupBy, $dateFrom, $dateTo, $customerId) as $r) {
    $row = [$r['group_label'], (int) $r['orders'], (int) $r['units'], (int) $r['units_shipped']];
    if ($canPrice) {
        $row[] = reports_csv_num($r['value'], 2);
    }
    $rows[] = $row;
}
$headers = [REPORT_ORDER_GROUP_BY[$groupBy], 'Orders', 'Units ordered', 'Units shipped'];
if ($canPrice) {
    $headers[] = 'Value ($)';
}
$name = 'orders';
log_activity($pdo, 'report_exported', 'report', null, $name, null, null,
    ['report' => $name, 'filters' => ['group_by' => $groupBy, 'date_from' => $dateFrom, 'date_to' => $dateTo, 'customer_id' => $customerId], 'rows' => count($rows)], 'report-' . $name);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $name . '-' . today() . '.csv"');
header('Cache-Control: no-store');
echo view('reports/csv.php', ['headers' => $headers, 'rows' => $rows]);
