<?php
declare(strict_types=1);

// Forecast demand: units per format per ISO week. Run-rate rows come from order history and are regenerated;
// manual rows are the user's figures and survive regeneration. app.v_demand counts only the part of a week's
// forecast that firm and standing demand do not already cover.

require_once __DIR__ . '/../orders/queries.php';

const FORECAST_DEFAULT_HISTORY_WEEKS = 12;
const FORECAST_DEFAULT_HORIZON_WEEKS = 12;
/** Order statuses whose lines count as history for the run rate (drafts and cancelled orders do not). */
const FORECAST_HISTORY_STATUSES = ['confirmed', 'in_fulfillment', 'shipped', 'closed'];

/** Monday of the current week. */
function forecast_week_start(?string $date = null): string
{
    return (new DateTimeImmutable($date ?? 'today'))->modify('monday this week')->format('Y-m-d');
}

/** The weeks shown: $weeks Mondays from the current week. */
function forecast_weeks(int $weeks): array
{
    $start = new DateTimeImmutable(forecast_week_start());
    return array_map(static fn($i) => $start->modify('+' . (7 * $i) . ' days')->format('Y-m-d'), range(0, $weeks - 1));
}

/** Average units ordered per week over the $historyWeeks before this week, per format: config id => [units, weekly]. */
function forecast_run_rates(PDO $pdo, int $historyWeeks): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT ol.packaging_configuration_id, SUM(ol.units_ordered) AS units
        FROM app.sales_order_lines ol JOIN app.sales_orders so ON so.id = ol.sales_order_id
        WHERE so.status = ANY (string_to_array(:statuses, ',')) AND ol.status <> 'cancelled'
          AND so.requested_on >= CAST(:week AS date) - 7 * CAST(:weeks AS int) AND so.requested_on < CAST(:week AS date)
        GROUP BY 1
    SQL);
    $statement->execute(['statuses' => implode(',', FORECAST_HISTORY_STATUSES), 'week' => forecast_week_start(), 'weeks' => $historyWeeks]);
    $out = [];
    foreach ($statement->fetchAll() as $row) {
        $out[(int) $row['packaging_configuration_id']] = ['units' => (int) $row['units'], 'weekly' => round((int) $row['units'] / $historyWeeks, 2)];
    }
    return $out;
}

/**
 * Replace the run-rate forecast from this week on: one row per format and week of the horizon at the format's
 * weekly rate, except weeks with a manual figure. Returns the rates used.
 */
function forecast_generate(PDO $pdo, int $historyWeeks, int $horizonWeeks, int $userId): array
{
    $rates = forecast_run_rates($pdo, $historyWeeks);
    $pdo->prepare("DELETE FROM app.demand_forecasts WHERE method = 'run_rate' AND week_start >= :w")->execute(['w' => forecast_week_start()]);
    $insert = $pdo->prepare(<<<'SQL'
        INSERT INTO app.demand_forecasts (packaging_configuration_id, week_start, units, method, history_weeks, created_by)
        VALUES (:c, :w, :u, 'run_rate', :h, :by)
        ON CONFLICT (packaging_configuration_id, week_start) DO NOTHING
    SQL);
    foreach ($rates as $configId => $rate) {
        if ($rate['weekly'] <= 0) {
            continue;
        }
        foreach (forecast_weeks($horizonWeeks) as $week) {
            $insert->execute(['c' => $configId, 'w' => $week, 'u' => $rate['weekly'], 'h' => $historyWeeks, 'by' => $userId]);
        }
    }
    return $rates;
}

/** Set a manual figure for one format and week, or clear it (null). Returns the previous row, if any. */
function forecast_set(PDO $pdo, int $configurationId, string $weekStart, ?float $units, int $userId): ?array
{
    $before = $pdo->prepare('SELECT units, method FROM app.demand_forecasts WHERE packaging_configuration_id = :c AND week_start = :w');
    $before->execute(['c' => $configurationId, 'w' => $weekStart]);
    $previous = $before->fetch() ?: null;
    if ($units === null) {
        $pdo->prepare("DELETE FROM app.demand_forecasts WHERE packaging_configuration_id = :c AND week_start = :w AND method = 'manual'")
            ->execute(['c' => $configurationId, 'w' => $weekStart]);
        return $previous;
    }
    $pdo->prepare(<<<'SQL'
        INSERT INTO app.demand_forecasts (packaging_configuration_id, week_start, units, method, created_by)
        VALUES (:c, :w, :u, 'manual', :by)
        ON CONFLICT (packaging_configuration_id, week_start) DO UPDATE SET units = EXCLUDED.units, method = 'manual', history_weeks = NULL, created_by = EXCLUDED.created_by
    SQL)->execute(['c' => $configurationId, 'w' => $weekStart, 'u' => $units, 'by' => $userId]);
    return $previous;
}

/**
 * The forecast grid: config id => [config, weeks => week => [firm, standing, forecast, method, counted]].
 * Firm and standing come from v_demand; counted is the forecast v_demand keeps after netting.
 */
function forecast_grid(PDO $pdo, array $weeks): array
{
    $grid = [];
    foreach (order_format_catalog($pdo) as $configId => $config) {
        $grid[$configId] = ['config' => $config, 'weeks' => array_fill_keys($weeks, ['firm' => 0.0, 'standing' => 0.0, 'forecast' => null, 'method' => null, 'counted' => 0.0])];
    }
    foreach ($pdo->query('SELECT demand_type, packaging_configuration_id, week_start, SUM(units) AS units FROM app.v_demand GROUP BY 1, 2, 3') as $row) {
        $c = (int) $row['packaging_configuration_id'];
        if (isset($grid[$c]['weeks'][$row['week_start']])) {
            $key = $row['demand_type'] === 'forecast' ? 'counted' : $row['demand_type'];
            $grid[$c]['weeks'][$row['week_start']][$key] += (float) $row['units'];
        }
    }
    $statement = $pdo->prepare('SELECT packaging_configuration_id, week_start, units, method FROM app.demand_forecasts WHERE week_start BETWEEN :a AND :b');
    $statement->execute(['a' => $weeks[0], 'b' => end($weeks)]);
    foreach ($statement->fetchAll() as $row) {
        $c = (int) $row['packaging_configuration_id'];
        if (isset($grid[$c]['weeks'][$row['week_start']])) {
            $grid[$c]['weeks'][$row['week_start']]['forecast'] = (float) $row['units'];
            $grid[$c]['weeks'][$row['week_start']]['method'] = $row['method'];
        }
    }
    return $grid;
}

/** When the run rate was last generated and from how many weeks of history. */
function forecast_last_generated(PDO $pdo): ?array
{
    $row = $pdo->query("SELECT MAX(created_at) AS at, MAX(history_weeks) AS weeks FROM app.demand_forecasts WHERE method = 'run_rate'")->fetch();
    return $row && $row['at'] !== null ? $row : null;
}
