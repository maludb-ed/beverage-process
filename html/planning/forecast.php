<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/planning/forecast.php';

$user = require_login();
$pdo = db();
$weeks = forecast_weeks(FORECAST_DEFAULT_HORIZON_WEEKS);
log_screen_entered('planning-forecast');
render_screen('Forecast', 'planning-forecast', view('planning/forecast.php', [
    'grid' => forecast_grid($pdo, $weeks), 'weeks' => $weeks, 'rates' => forecast_run_rates($pdo, FORECAST_DEFAULT_HISTORY_WEEKS),
    'last' => forecast_last_generated($pdo), 'canEdit' => user_can($user, 'sales'), 'canPrice' => user_can($user, 'sales'), 'errors' => [],
]));
