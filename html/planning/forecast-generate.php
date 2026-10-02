<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/planning/forecast.php';

// Regenerate the run-rate forecast from order history; manual figures stay.
require_post();
verify_csrf();
$user = require_role('sales');
$pdo = db();
$history = request_integer('history_weeks') ?? FORECAST_DEFAULT_HISTORY_WEEKS;
$horizon = request_integer('horizon_weeks') ?? FORECAST_DEFAULT_HORIZON_WEEKS;
if ($history < 1 || $history > 104 || $horizon < 1 || $horizon > 26) {
    flash('error', 'Use 1 to 104 weeks of history and 1 to 26 weeks ahead.');
    hx_location('/planning/forecast');
}
try {
    $pdo->beginTransaction();
    $rates = forecast_generate($pdo, $history, $horizon, (int) $user['id']);
    log_activity($pdo, 'forecast_generated', 'demand_forecast', null, 'Run rate', null,
        ['history_weeks' => $history, 'horizon_weeks' => $horizon, 'weekly_units' => array_map(static fn($r) => $r['weekly'], $rates)], [], 'planning-forecast');
    $pdo->commit();
    flash('success', $rates === [] ? 'No orders were due in the last ' . $history . ' weeks, so there is no run rate to forecast from.'
        : 'Forecast generated from ' . $history . ' weeks of orders for ' . count($rates) . ' formats, ' . $horizon . ' weeks ahead. Manual figures were kept.');
    hx_trigger('forecastChanged');
} catch (PDOException | RuntimeException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($exception->getMessage());
    flash('error', 'The forecast could not be generated.');
}
hx_location('/planning/forecast');
