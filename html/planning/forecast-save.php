<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/planning/forecast.php';

// Set or clear a manual forecast for one format and week.
require_post();
verify_csrf();
$user = require_role('sales');
$pdo = db();
$configId = request_integer('packaging_configuration_id');
$week = post_date('week_start');
$units = post_decimal('units');
$catalog = order_format_catalog($pdo);
if ($configId === null || !isset($catalog[$configId]) || !is_string($week) || $week !== forecast_week_start($week) || $units === false || ($units !== null && ($units < 0 || $units > 1000000))) {
    flash('error', 'Enter units of zero or more for a format and a week (blank clears a manual figure).');
    hx_location('/planning/forecast');
}
try {
    $pdo->beginTransaction();
    $before = forecast_set($pdo, $configId, $week, $units, (int) $user['id']);
    log_activity($pdo, 'forecast_set', 'demand_forecast', null, $catalog[$configId]['name'] . ' week of ' . $week, $before, $units === null ? null : ['units' => $units, 'method' => 'manual'],
        ['packaging_configuration_id' => $configId, 'week_start' => $week], 'planning-forecast');
    $pdo->commit();
    flash('success', $catalog[$configId]['name'] . ', week of ' . format_date($week) . ': ' . ($units === null ? 'manual figure cleared.' : number_format($units, 2) . ' units.'));
    hx_trigger('forecastChanged');
} catch (PDOException | RuntimeException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($exception->getMessage());
    flash('error', 'The forecast could not be saved.');
}
hx_location('/planning/forecast');
