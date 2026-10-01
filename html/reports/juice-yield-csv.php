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
$year = request_integer('season_year');
$year = $year !== null && in_array($year, $seasons, true) ? $year : $currentYear;
$variety = request_string('variety', 100);
$variety = in_array($variety, find_juice_yield_varieties($pdo), true) ? $variety : '';
$premisesId = reports_premises_param(premises_options($pdo));
$result = find_juice_yield_rows($pdo, $year, $variety ?: null, $premisesId, request_string('sort', 40) ?: 'run_on', 1, 1000000);
$vol = display_unit('L');
$mass = display_unit('kg', 'fruit');
$rows = [];
foreach ($result['rows'] as $r) {
    $rows[] = [$r['number'], (string) $r['run_on'], $r['variety'] ?? 'Unspecified', reports_csv_num(to_display($r['fruit_kg'], 'kg', 'fruit')), reports_csv_num(to_display($r['juice_l_attributed'], 'L')),
        reports_csv_num($r['gal_per_ton'], 2), reports_csv_num($r['gal_per_bushel'], 3)];
}
$headers = ['Press run', 'Date', 'Variety', "Fruit ($mass)", "Juice ($vol)", 'Gallons per ton', 'Gallons per bushel'];
$filters = ['season_year' => $year, 'variety' => $variety, 'premises_id' => $premisesId];
$name = 'juice-yield';

log_activity($pdo, 'report_exported', 'report', null, $name, null, null, ['report' => $name, 'filters' => $filters, 'rows' => count($rows)], 'report-' . $name);
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $name . '-' . today() . '.csv"');
header('Cache-Control: no-store');
echo view('reports/csv.php', ['headers' => $headers, 'rows' => $rows]);
