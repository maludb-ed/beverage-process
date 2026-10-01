<?php
declare(strict_types=1);

// Read-only report queries (slice 9). Nothing here writes.

const REPORT_YIELD_SORTS = ['entered_at' => 'y.entered_at', 'number' => 'y.number', 'stage_code' => 'y.stage_code'];
const REPORT_BATCH_COST_SORTS = ['started_at' => 'b.started_at', 'number' => 'c.number', 'total_cost' => 'c.total_cost', 'variance_to_standard' => 'c.variance_to_standard'];
const REPORT_JUICE_SORTS = ['run_on' => 'v.run_on', 'variety' => 'v.variety', 'gal_per_ton' => 'v.gal_per_ton'];
const REPORT_VALUATION_SORTS = ['group' => 'group_label', 'value' => 'value'];
const REPORT_BATCH_STATUSES = ['active' => 'Active', 'packaged' => 'Packaged', 'closed' => 'Closed', 'dumped' => 'Dumped'];
const REPORT_BATCH_STATUS_COLORS = ['packaged' => 'success', 'dumped' => 'danger', 'active' => 'info', 'closed' => 'secondary'];
const REPORT_GROUP_BY = ['item_class' => 'Item class', 'tax_state' => 'Tax state', 'location' => 'Location'];
const REPORT_BUSHEL_KG = 19.05087954;

/** Active and retired products (not drafts) for filter selects: id => "CODE name". */
function find_report_products(PDO $pdo): array
{
    $rows = $pdo->query("SELECT id, code, name FROM app.products WHERE status IN ('active','retired') ORDER BY name")->fetchAll();
    return array_column(array_map(static fn(array $r): array => ['id' => $r['id'], 'label' => $r['name']], $rows), 'label', 'id');
}

/** Cider stages for the yield filter: code => name. */
function find_report_stages(PDO $pdo): array
{
    $rows = $pdo->query("SELECT code, name FROM app.stages WHERE 'cider' = ANY(beverage_types) ORDER BY display_order")->fetchAll();
    return array_column($rows, 'name', 'code');
}

// ---- Yields -------------------------------------------------------------------------

function report_yield_where(?int $productId, string $batchSearch, ?string $dateFrom, ?string $dateTo, ?string $stageCode): array
{
    $where = [];
    $params = [];
    if ($productId !== null) { $where[] = 'b.product_id = :product'; $params['product'] = $productId; }
    if ($batchSearch !== '') { $where[] = 'y.number ILIKE :search'; $params['search'] = '%' . $batchSearch . '%'; }
    if ($dateFrom !== null) { $where[] = 'b.started_at >= :date_from::date'; $params['date_from'] = $dateFrom; }
    if ($dateTo !== null) { $where[] = 'b.started_at < (:date_to::date + 1)'; $params['date_to'] = $dateTo; }
    if ($stageCode !== null) { $where[] = 'y.stage_code = :stage'; $params['stage'] = $stageCode; }
    return [$where === [] ? '' : ' WHERE ' . implode(' AND ', $where), $params];
}

function find_yield_rows(PDO $pdo, ?int $productId, string $batchSearch, ?string $dateFrom, ?string $dateTo, ?string $stageCode, string $sort = 'entered_at', int $page = 1, int $pageSize = 100): array
{
    [$whereSql, $params] = report_yield_where($productId, $batchSearch, $dateFrom, $dateTo, $stageCode);
    $from = ' FROM app.v_batch_stage_yields y JOIN app.batches b ON b.id = y.batch_id JOIN app.products p ON p.id = b.product_id';
    return paged_query($pdo,
        'SELECT y.*, b.product_id, b.status, b.started_at, p.name AS product_name' . $from . $whereSql
            . ' ORDER BY ' . order_by($sort, REPORT_YIELD_SORTS, 'entered_at') . ', y.batch_id, y.entered_at',
        'SELECT count(*)' . $from . $whereSql, $params, $page, $pageSize);
}

/** Batch totals for the yield report: batch_id => [recorded_loss_l, volume_in_l, volume_out_l, loss_pct]. Uses every stage of the batch. */
function find_batch_yield_totals(PDO $pdo, array $batchIds): array
{
    if ($batchIds === []) {
        return [];
    }
    $in = implode(',', array_map('intval', $batchIds));
    $rows = $pdo->query("SELECT batch_id, volume_in_l, volume_out_l, recorded_loss_l, stage_code FROM app.v_batch_stage_yields WHERE batch_id IN ($in) ORDER BY batch_id, entered_at")->fetchAll();
    $totals = [];
    $seenStage = [];
    foreach ($rows as $r) {
        $id = (int) $r['batch_id'];
        $totals[$id] ??= ['recorded_loss_l' => 0.0, 'volume_in_l' => null, 'volume_out_l' => null, 'loss_pct' => null];
        if (!isset($seenStage[$id][$r['stage_code']])) {
            $totals[$id]['recorded_loss_l'] += (float) $r['recorded_loss_l'];
            $seenStage[$id][$r['stage_code']] = true;
        }
        if ($totals[$id]['volume_in_l'] === null && $r['volume_in_l'] !== null) {
            $totals[$id]['volume_in_l'] = (float) $r['volume_in_l'];
        }
        if ($r['volume_out_l'] !== null) {
            $totals[$id]['volume_out_l'] = (float) $r['volume_out_l'];
        }
    }
    foreach ($totals as &$t) {
        if ($t['volume_in_l'] > 0 && $t['volume_out_l'] !== null) {
            $t['loss_pct'] = 100 * ($t['volume_in_l'] - $t['volume_out_l']) / $t['volume_in_l'];
        }
    }
    return $totals;
}

// ---- Juice yield ---------------------------------------------------------------------

function find_juice_yield_seasons(PDO $pdo): array
{
    return array_map('intval', $pdo->query("SELECT DISTINCT extract(year FROM run_on)::int FROM app.v_press_run_yields ORDER BY 1 DESC")->fetchAll(PDO::FETCH_COLUMN));
}

function find_juice_yield_varieties(PDO $pdo): array
{
    return $pdo->query("SELECT DISTINCT variety FROM app.v_press_run_yields WHERE variety IS NOT NULL ORDER BY 1")->fetchAll(PDO::FETCH_COLUMN);
}

function report_juice_where(int $seasonYear, ?string $variety, ?int $premisesId): array
{
    $where = ['v.run_on >= make_date(:year, 1, 1)', 'v.run_on < make_date(:year_next, 1, 1)'];
    $params = ['year' => $seasonYear, 'year_next' => $seasonYear + 1];
    if ($variety !== null) { $where[] = 'v.variety = :variety'; $params['variety'] = $variety; }
    if ($premisesId !== null) { $where[] = 'v.premises_id = :premises'; $params['premises'] = $premisesId; }
    return [' WHERE ' . implode(' AND ', $where), $params];
}

function find_juice_yield_summary(PDO $pdo, int $seasonYear, ?string $variety, ?int $premisesId): array
{
    [$whereSql, $params] = report_juice_where($seasonYear, $variety, $premisesId);
    $statement = $pdo->prepare('SELECT v.variety, count(DISTINCT v.press_run_id) AS press_runs, sum(v.fruit_kg) AS fruit_kg, sum(v.juice_l_attributed) AS juice_l,
            sum(v.fruit_kg) / ' . KG_PER_TON . ' AS tons,
            CASE WHEN sum(v.fruit_kg) > 0 THEN (sum(v.juice_l_attributed) / ' . LITERS_PER_GALLON . ') / (sum(v.fruit_kg) / ' . KG_PER_TON . ') END AS gal_per_ton,
            CASE WHEN sum(v.fruit_kg) > 0 THEN (sum(v.juice_l_attributed) / ' . LITERS_PER_GALLON . ') / (sum(v.fruit_kg) / ' . REPORT_BUSHEL_KG . ') END AS gal_per_bushel
        FROM app.v_press_run_yields v' . $whereSql . ' GROUP BY v.variety ORDER BY v.variety NULLS LAST');
    $statement->execute($params);
    return $statement->fetchAll();
}

function find_juice_yield_rows(PDO $pdo, int $seasonYear, ?string $variety, ?int $premisesId, string $sort = 'run_on', int $page = 1, int $pageSize = 100): array
{
    [$whereSql, $params] = report_juice_where($seasonYear, $variety, $premisesId);
    return paged_query($pdo,
        'SELECT v.* FROM app.v_press_run_yields v' . $whereSql . ' ORDER BY ' . order_by($sort, REPORT_JUICE_SORTS, 'run_on') . ', v.press_run_id',
        'SELECT count(*) FROM app.v_press_run_yields v' . $whereSql, $params, $page, $pageSize);
}

// ---- Batch costs ---------------------------------------------------------------------

function report_cost_where(?int $productId, array $statuses, ?string $dateFrom, ?string $dateTo): array
{
    $where = [];
    $params = [];
    if ($productId !== null) { $where[] = 'c.product_id = :product'; $params['product'] = $productId; }
    $statuses = array_values(array_intersect($statuses, array_keys(REPORT_BATCH_STATUSES)));
    if ($statuses === []) {
        $where[] = "c.status <> 'dumped'";
    } else {
        $marks = [];
        foreach ($statuses as $i => $status) { $marks[] = ':st' . $i; $params['st' . $i] = $status; }
        $where[] = 'c.status IN (' . implode(',', $marks) . ')';
    }
    if ($dateFrom !== null) { $where[] = 'b.started_at >= :date_from::date'; $params['date_from'] = $dateFrom; }
    if ($dateTo !== null) { $where[] = 'b.started_at < (:date_to::date + 1)'; $params['date_to'] = $dateTo; }
    return [' WHERE ' . implode(' AND ', $where), $params];
}

function find_batch_cost_rows(PDO $pdo, ?int $productId, array $statuses, ?string $dateFrom, ?string $dateTo, string $sort = 'started_at', int $page = 1, int $pageSize = 50): array
{
    [$whereSql, $params] = report_cost_where($productId, $statuses, $dateFrom, $dateTo);
    $from = ' FROM app.v_batch_costs c JOIN app.batches b ON b.id = c.batch_id JOIN app.products p ON p.id = c.product_id';
    return paged_query($pdo,
        'SELECT c.*, b.started_at, p.name AS product_name' . $from . $whereSql . ' ORDER BY ' . order_by($sort, REPORT_BATCH_COST_SORTS, 'started_at') . ', c.batch_id',
        'SELECT count(*)' . $from . $whereSql, $params, $page, $pageSize);
}

/** Footer sums over every matching batch (not just the page). */
function find_batch_cost_totals(PDO $pdo, ?int $productId, array $statuses, ?string $dateFrom, ?string $dateTo): array
{
    [$whereSql, $params] = report_cost_where($productId, $statuses, $dateFrom, $dateTo);
    $statement = $pdo->prepare('SELECT count(*) AS batches, COALESCE(sum(c.material_cost),0) AS material_cost, COALESCE(sum(c.packaging_cost),0) AS packaging_cost,
            COALESCE(sum(c.overhead_cost),0) AS overhead_cost, COALESCE(sum(c.total_cost),0) AS total_cost, COALESCE(sum(c.variance_to_standard),0) AS variance_to_standard
        FROM app.v_batch_costs c JOIN app.batches b ON b.id = c.batch_id' . $whereSql);
    $statement->execute($params);
    return $statement->fetch();
}

/** batch_id => ['per_keg' => ?float, 'per_case' => ?float]. */
function find_batch_package_costs(PDO $pdo, array $batchIds): array
{
    $out = [];
    if ($batchIds === []) {
        return $out;
    }
    $in = implode(',', array_map('intval', $batchIds));
    $rows = $pdo->query("SELECT fl.batch_id,
            min(fl.unit_cost) FILTER (WHERE pc.package_kind = 'keg') AS per_keg,
            min(fl.unit_cost * pc.units_per_case) FILTER (WHERE pc.package_kind = 'can' AND pc.units_per_case IS NOT NULL) AS per_case
        FROM app.finished_lots fl JOIN app.packaging_configurations pc ON pc.id = fl.packaging_configuration_id
        WHERE fl.batch_id IN ($in) GROUP BY fl.batch_id")->fetchAll();
    foreach ($rows as $r) {
        $out[(int) $r['batch_id']] = ['per_keg' => $r['per_keg'] === null ? null : (float) $r['per_keg'], 'per_case' => $r['per_case'] === null ? null : (float) $r['per_case']];
    }
    return $out;
}

// ---- Valuation -----------------------------------------------------------------------

/** Source rows for valuation: item_class, tax_state, premises_id, location_id, location_name, base_unit_code, qty_on_hand, value. */
function report_valuation_source(string $asOf, string $groupBy): string
{
    $costExpr = "CASE WHEN i.costing_method = 'standard' THEN COALESCE(i.standard_cost_per_base, 0) ELSE l.unit_cost_base END";
    if ($asOf < today()) {
        // Ledger replay: balance per item, lot, location as of the end of $asOf.
        return "SELECT i.item_class, loc.tax_state, loc.premises_id, loc.id AS location_id, loc.name AS location_name, i.base_unit_code,
                       t.qty AS qty_on_hand, t.qty * $costExpr AS value
                  FROM (SELECT item_id, lot_id, location_id, sum(qty_base) AS qty FROM app.inventory_transactions
                         WHERE occurred_at < (:as_of::date + 1) GROUP BY item_id, lot_id, location_id HAVING sum(qty_base) <> 0) t
                  JOIN app.items i ON i.id = t.item_id JOIN app.lots l ON l.id = t.lot_id JOIN app.locations loc ON loc.id = t.location_id";
    }
    if ($groupBy === 'location') {
        return "SELECT i.item_class, loc.tax_state, loc.premises_id, loc.id AS location_id, loc.name AS location_name, i.base_unit_code,
                       b.qty_on_hand, b.qty_on_hand * $costExpr AS value
                  FROM app.inventory_balances b JOIN app.items i ON i.id = b.item_id JOIN app.lots l ON l.id = b.lot_id
                  JOIN app.locations loc ON loc.id = b.location_id WHERE b.qty_on_hand <> 0";
    }
    return "SELECT item_class, tax_state, premises_id, NULL::bigint AS location_id, NULL::text AS location_name, base_unit_code, qty_on_hand, value FROM app.v_inventory_valuation";
}

function report_valuation_params(string $asOf, ?int $premisesId): array
{
    $params = [];
    if ($asOf < today()) { $params['as_of'] = $asOf; }
    if ($premisesId !== null) { $params['premises'] = $premisesId; }
    return $params;
}

/** Rows: group_key, group_label, base_unit_code, qty_on_hand, value, location_id (location grouping only). */
function find_valuation_rows(PDO $pdo, string $asOf, ?int $premisesId, string $groupBy, string $sort = 'value'): array
{
    $groupBy = array_key_exists($groupBy, REPORT_GROUP_BY) ? $groupBy : 'item_class';
    $source = report_valuation_source($asOf, $groupBy);
    $keyExpr = match ($groupBy) { 'tax_state' => 's.tax_state', 'location' => 's.location_name', default => 's.item_class' };
    $statement = $pdo->prepare("SELECT $keyExpr AS group_label, " . ($groupBy === 'location' ? 'min(s.location_id)' : 'NULL::bigint') . " AS location_id,
            s.base_unit_code, sum(s.qty_on_hand) AS qty_on_hand, sum(s.value) AS value
        FROM ($source) s" . ($premisesId !== null ? ' WHERE s.premises_id = :premises' : '') . "
        GROUP BY 1, s.base_unit_code ORDER BY " . order_by($sort, ['group' => 'group_label', 'value' => 'value'], 'value') . ', s.base_unit_code');
    $statement->execute(report_valuation_params($asOf, $premisesId));
    return $statement->fetchAll();
}

/** Bonded vs tax paid totals regardless of grouping. */
function find_valuation_by_tax_state(PDO $pdo, string $asOf, ?int $premisesId): array
{
    $source = report_valuation_source($asOf, 'tax_state');
    $statement = $pdo->prepare("SELECT s.tax_state, COALESCE(sum(s.value), 0) AS value FROM ($source) s" . ($premisesId !== null ? ' WHERE s.premises_id = :premises' : '') . ' GROUP BY s.tax_state');
    $statement->execute(report_valuation_params($asOf, $premisesId));
    $found = array_column($statement->fetchAll(), 'value', 'tax_state');
    return ['bonded' => (float) ($found['bonded'] ?? 0), 'tax_paid' => (float) ($found['tax_paid'] ?? 0)];
}
