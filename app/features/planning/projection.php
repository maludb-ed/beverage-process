<?php
declare(strict_types=1);

// Projections: week-by-week netting from customer demand to packaging, bulk cider (production) and materials
// (purchasing), for one demand level: firm, firm + standing, or all (with forecast). Computed on request from
// app.v_demand and current stock, batches, draft packaging runs, unstarted production orders and open
// purchase orders. Nothing is written.
//
//   finished goods  : released units + draft packaging runs (their run week) − demand  → units to package
//   bulk cider      : active batches (at their ready week, after the recipe's remaining losses)
//                     + unstarted production orders (at their package week) − liters to package → batches to start
//   materials       : released stock + open purchase order lines (at their expected week)
//                     − packaging bills of materials, recipe lines of suggested batches and unstarted orders,
//                       and draft packaging runs → quantities to buy, with order-by dates from supplier lead times

require_once __DIR__ . '/forecast.php';
require_once __DIR__ . '/../packaging-runs/queries.php';

const PLANNING_LEVELS = ['firm' => 'Firm orders', 'standing' => 'Firm and standing', 'all' => 'All, with forecast'];
// 'planned' is internal: no customer demand at all, so what remains comes from production orders and draft runs already planned.
const PLANNING_LEVEL_TYPES = ['planned' => [], 'firm' => ['firm'], 'standing' => ['firm', 'standing'], 'all' => ['firm', 'standing', 'forecast']];
const PLANNING_DEFAULT_WEEKS = 12;
/** Batches in these stages can be packaged now; earlier stages wait for the recipe's remaining durations. */
const PLANNING_READY_STAGES = ['carbonate', 'package'];

/** The index of the week holding $date (dates before the first week fall in week 0; after the last, null). */
function planning_week_index(array $weeks, ?string $date): ?int
{
    if ($date === null || $date < $weeks[0]) {
        return 0;
    }
    $index = intdiv((int) ((strtotime($date) - strtotime($weeks[0])) / 86400), 7);
    return $index < count($weeks) ? $index : null;
}

/** Active recipe versions by product with their stages (in order) and lines. */
function planning_recipes(PDO $pdo): array
{
    $recipes = [];
    foreach ($pdo->query("SELECT id, product_id, version_no, target_batch_volume_l, expected_total_loss_pct FROM app.recipe_versions WHERE status IN ('active', 'retired', 'draft') ORDER BY id") as $row) {
        $recipes[(int) $row['id']] = $row + ['stages' => [], 'lines' => []];
    }
    foreach ($pdo->query('SELECT recipe_version_id, seq, stage_code, expected_loss_pct, expected_duration_days FROM app.recipe_stages ORDER BY recipe_version_id, seq') as $row) {
        if (isset($recipes[(int) $row['recipe_version_id']])) {
            $recipes[(int) $row['recipe_version_id']]['stages'][] = $row;
        }
    }
    foreach ($pdo->query('SELECT rl.recipe_version_id, rl.item_id, rl.stage_code, rl.qty_per_batch_base, rl.qty_per_l FROM app.recipe_lines rl') as $row) {
        if (isset($recipes[(int) $row['recipe_version_id']])) {
            $recipes[(int) $row['recipe_version_id']]['lines'][] = $row;
        }
    }
    return $recipes;
}

/** The active recipe version id of each product. */
function planning_active_recipes(PDO $pdo): array
{
    return array_map('intval', array_column($pdo->query("SELECT product_id, id FROM app.recipe_versions WHERE status = 'active'")->fetchAll(), 'id', 'product_id'));
}

/**
 * From a stage (or from pitch when null) to ready for packaging: days, the share of volume left after the
 * stages' expected losses, and how many of those stages have no duration. Stages from 'package' on do not count.
 */
function planning_remaining_path(array $recipe, ?string $fromStage, int $daysInStage = 0): array
{
    $days = 0;
    $keep = 1.0;
    $missing = 0;
    $started = $fromStage === null;
    foreach ($recipe['stages'] as $stage) {
        if ($stage['stage_code'] === 'package') {
            break;
        }
        if (!$started) {
            if ($stage['stage_code'] !== $fromStage) {
                continue;
            }
            $started = true;
            if ($stage['expected_duration_days'] === null) {
                $missing++;
            } else {
                $days += max(0, (int) $stage['expected_duration_days'] - $daysInStage);
            }
            continue;   // the current stage's loss is already in the batch's volume
        }
        if ($fromStage === null && in_array($stage['stage_code'], ['fruit', 'press', 'juice', 'juice_blend'], true)) {
            continue;   // a production order starts at pitch
        }
        $keep *= 1 - (float) $stage['expected_loss_pct'] / 100;
        if ($stage['expected_duration_days'] === null) {
            $missing++;
        } else {
            $days += (int) $stage['expected_duration_days'];
        }
    }
    return ['days' => $days, 'keep' => $keep, 'missing' => $missing, 'found' => $started];
}

/** Everything the projection reads, once (shared by the three demand levels). */
function planning_inputs(PDO $pdo, int $weeksCount): array
{
    $weeks = forecast_weeks($weeksCount);
    $today = today();
    $configs = [];
    foreach ($pdo->query('SELECT pc.id, pc.name, pc.product_id, pc.fill_volume_l, pc.expected_loss_pct, pc.default_unit_price, p.name AS product_name FROM app.packaging_configurations pc JOIN app.products p ON p.id = pc.product_id') as $row) {
        $configs[(int) $row['id']] = $row + ['bom' => []];
    }
    foreach ($pdo->query('SELECT configuration_id, item_id, qty_per_unit_base FROM app.packaging_bom_lines') as $row) {
        $configs[(int) $row['configuration_id']]['bom'][(int) $row['item_id']] = (float) $row['qty_per_unit_base'];
    }
    $demand = [];
    foreach ($pdo->query('SELECT demand_type, packaging_configuration_id, week_start, SUM(units) AS units, SUM(value) AS value FROM app.v_demand GROUP BY 1, 2, 3') as $row) {
        $w = planning_week_index($weeks, $row['week_start']);
        if ($w !== null) {
            $demand[] = ['type' => $row['demand_type'], 'config' => (int) $row['packaging_configuration_id'], 'week' => $w, 'units' => (float) $row['units'], 'value' => $row['value'] === null ? null : (float) $row['value']];
        }
    }
    $finished = [];
    foreach ($pdo->query("SELECT fs.packaging_configuration_id, SUM(fs.units_available) AS units FROM app.v_finished_stock fs JOIN app.lots l ON l.id = fs.lot_id WHERE l.quality_status = 'released' GROUP BY 1") as $row) {
        $finished[(int) $row['packaging_configuration_id']] = (float) $row['units'];
    }
    $draftRuns = $pdo->query("SELECT id, number, batch_id, packaging_configuration_id, run_on, COALESCE(units_out, 0) AS units, COALESCE(volume_in_l, 0) AS volume_l FROM app.packaging_runs WHERE status = 'draft'")->fetchAll();
    $batches = $pdo->query(<<<'SQL'
        SELECT b.id, b.number, b.product_id, b.recipe_version_id, b.current_stage_code, b.current_volume_l, b.production_order_id,
               GREATEST(0, current_date - COALESCE((SELECT MAX(s.entered_at) FROM app.stage_events s WHERE s.batch_id = b.id AND s.left_at IS NULL), b.started_at)::date) AS days_in_stage
        FROM app.batches b WHERE b.status = 'active' AND b.current_volume_l > 0
    SQL)->fetchAll();
    $orders = $pdo->query(<<<'SQL'
        SELECT po.id, po.number, po.status, po.product_id, po.recipe_version_id, po.planned_volume_l, po.planned_pitch_on, po.planned_package_on
        FROM app.production_orders po
        WHERE po.status IN ('planned', 'released', 'in_progress') AND NOT EXISTS (SELECT 1 FROM app.batches b WHERE b.production_order_id = po.id)
    SQL)->fetchAll();
    $stock = [];
    foreach ($pdo->query("SELECT item_id, SUM(qty_on_hand) AS qty FROM app.v_lot_balances WHERE quality_status = 'released' GROUP BY 1") as $row) {
        $stock[(int) $row['item_id']] = (float) $row['qty'];
    }
    $receipts = $pdo->query('SELECT item_id, number, qty_outstanding_base, expected_on FROM app.v_open_po_lines')->fetchAll();
    $items = [];
    foreach ($pdo->query('SELECT id, code, name, item_class, base_unit_code FROM app.items') as $row) {
        $items[(int) $row['id']] = $row;
    }
    $suppliers = [];
    foreach ($pdo->query(<<<'SQL'
        SELECT DISTINCT ON (si.item_id) si.item_id, s.name AS supplier_name, si.lead_time_days, si.purchase_unit_code, si.to_base_factor, si.last_price
        FROM app.supplier_items si JOIN app.suppliers s ON s.id = si.supplier_id
        WHERE si.active AND s.active ORDER BY si.item_id, si.lead_time_days NULLS LAST, si.last_price NULLS LAST, s.name
    SQL) as $row) {
        $suppliers[(int) $row['item_id']] = $row;
    }
    $yield = $pdo->query('SELECT SUM(juice_l_attributed) / NULLIF(SUM(fruit_kg), 0) FROM app.v_press_run_yields')->fetchColumn();
    return ['weeks' => $weeks, 'today' => $today, 'configs' => $configs, 'demand' => $demand, 'finished' => $finished, 'draft_runs' => $draftRuns,
            'batches' => $batches, 'orders' => $orders, 'stock' => $stock, 'receipts' => $receipts, 'items' => $items, 'suppliers' => $suppliers,
            'recipes' => planning_recipes($pdo), 'active_recipes' => planning_active_recipes($pdo), 'juice_l_per_kg' => $yield === null || $yield === false ? null : (float) $yield];
}

/** Run the netting for one demand level. */
function planning_run(array $in, string $level): array
{
    $weeks = $in['weeks'];
    $n = count($weeks);
    $zero = array_fill(0, $n, 0.0);
    $types = PLANNING_LEVEL_TYPES[$level];
    $warnings = [];

    // Demand by format and week, and the totals by type for the overview.
    $demand = [];
    $byType = ['firm' => $zero, 'standing' => $zero, 'forecast' => $zero];
    $valueByType = ['firm' => $zero, 'standing' => $zero, 'forecast' => $zero];
    foreach ($in['demand'] as $d) {
        if (!in_array($d['type'], $types, true)) {
            continue;
        }
        $demand[$d['config']] ??= $zero;
        $demand[$d['config']][$d['week']] += $d['units'];
        $byType[$d['type']][$d['week']] += $d['units'];
        $valueByType[$d['type']][$d['week']] += (float) $d['value'];
    }

    // (1) Finished goods: what must be packaged, by format and week.
    $draftByConfig = [];
    foreach ($in['draft_runs'] as $run) {
        $w = planning_week_index($weeks, $run['run_on']);
        if ($w !== null) {
            $draftByConfig[(int) $run['packaging_configuration_id']] ??= $zero;
            $draftByConfig[(int) $run['packaging_configuration_id']][$w] += (float) $run['units'];
        }
    }
    $packaging = [];
    foreach ($demand as $configId => $need) {
        $available = $in['finished'][$configId] ?? 0.0;
        $toPackage = $zero;
        for ($w = 0; $w < $n; $w++) {
            $available += $draftByConfig[$configId][$w] ?? 0.0;
            $available -= $need[$w];
            if ($available < -0.0001) {
                $toPackage[$w] = ceil(-$available - 0.0001);
                $available = 0.0;
            }
        }
        $packaging[$configId] = ['demand' => $need, 'on_hand' => $in['finished'][$configId] ?? 0.0, 'draft_runs' => $draftByConfig[$configId] ?? $zero, 'to_package' => $toPackage];
    }

    // (2) Bulk cider: liters to package against batches and unstarted production orders, by product and week.
    $bulkNeed = [];
    foreach ($packaging as $configId => $p) {
        $config = $in['configs'][$configId];
        $perUnit = (float) $config['fill_volume_l'] / (1 - min(99.0, (float) $config['expected_loss_pct']) / 100);
        $bulkNeed[(int) $config['product_id']] ??= $zero;
        foreach ($p['to_package'] as $w => $units) {
            $bulkNeed[(int) $config['product_id']][$w] += $units * $perUnit;
        }
    }
    $draftVolumeByBatch = [];
    foreach ($in['draft_runs'] as $run) {
        $draftVolumeByBatch[(int) $run['batch_id']] = ($draftVolumeByBatch[(int) $run['batch_id']] ?? 0.0) + (float) $run['volume_l'];
    }
    $bulkSupply = [];
    $supplyRows = [];
    foreach ($in['batches'] as $batch) {
        $volume = max(0.0, (float) $batch['current_volume_l'] - ($draftVolumeByBatch[(int) $batch['id']] ?? 0.0));
        if ($volume <= 0) {
            continue;
        }
        $recipe = $in['recipes'][(int) $batch['recipe_version_id']] ?? $in['recipes'][$in['active_recipes'][(int) $batch['product_id']] ?? 0] ?? null;
        $ready = $in['today'];
        if (!in_array($batch['current_stage_code'], PLANNING_READY_STAGES, true)) {
            $path = $recipe ? planning_remaining_path($recipe, $batch['current_stage_code'], (int) $batch['days_in_stage']) : null;
            if ($path === null || !$path['found']) {
                $warnings[] = 'Batch ' . $batch['number'] . ' (' . humanize($batch['current_stage_code']) . ') has no recipe stage to time it from; counted as ready now.';
            } else {
                $volume *= $path['keep'];
                $ready = date('Y-m-d', strtotime($in['today'] . ' +' . $path['days'] . ' days'));
                if ($path['missing'] > 0) {
                    $warnings[] = 'Batch ' . $batch['number'] . ': ' . $path['missing'] . ' remaining recipe stages have no duration, so its ready date may be early.';
                }
            }
        }
        $w = planning_week_index($in['weeks'], $ready);
        $supplyRows[] = ['kind' => 'batch', 'ref' => $batch['number'], 'id' => (int) $batch['id'], 'product_id' => (int) $batch['product_id'], 'ready_on' => $ready, 'volume_l' => $volume, 'week' => $w];
        if ($w !== null) {
            $bulkSupply[(int) $batch['product_id']] ??= $zero;
            $bulkSupply[(int) $batch['product_id']][$w] += $volume;
        }
    }
    foreach ($in['orders'] as $order) {
        $recipe = $in['recipes'][(int) $order['recipe_version_id']] ?? null;
        $path = $recipe ? planning_remaining_path($recipe, null) : ['days' => 0, 'keep' => 1.0, 'missing' => 0];
        $packageOn = $order['planned_package_on'] ?? ($order['planned_pitch_on'] ? date('Y-m-d', strtotime($order['planned_pitch_on'] . ' +' . $path['days'] . ' days')) : null);
        if ($packageOn === null) {
            $warnings[] = 'Production order ' . $order['number'] . ' has no pitch or package date and is left out of the bulk supply.';
            continue;
        }
        if ($order['planned_pitch_on'] !== null && $order['planned_pitch_on'] < $in['today']) {
            $warnings[] = 'Production order ' . $order['number'] . ' was planned to pitch on ' . format_date($order['planned_pitch_on']) . ' and has no batch yet; it still counts as bulk supply.';
        }
        $volume = (float) $order['planned_volume_l'] * $path['keep'];
        $w = planning_week_index($in['weeks'], max($packageOn, $in['today']));
        $supplyRows[] = ['kind' => 'production_order', 'ref' => $order['number'], 'id' => (int) $order['id'], 'product_id' => (int) $order['product_id'], 'ready_on' => $packageOn, 'volume_l' => $volume, 'week' => $w];
        if ($w !== null) {
            $bulkSupply[(int) $order['product_id']] ??= $zero;
            $bulkSupply[(int) $order['product_id']][$w] += $volume;
        }
    }
    $bulk = [];
    $production = [];
    foreach ($bulkNeed as $productId => $need) {
        $recipeId = $in['active_recipes'][$productId] ?? null;
        $recipe = $recipeId ? $in['recipes'][$recipeId] : null;
        $path = $recipe ? planning_remaining_path($recipe, null) : null;
        $available = 0.0;
        $short = $zero;
        $planned = $zero;
        for ($w = 0; $w < $n; $w++) {
            $available += $bulkSupply[$productId][$w] ?? 0.0;
            $available -= $need[$w];
            if ($available < -0.001) {
                $shortfall = -$available;
                $short[$w] = $shortfall;
                if ($recipe === null || $path === null) {
                    $available = 0.0;
                    continue;
                }
                $target = (float) $recipe['target_batch_volume_l'];
                $yieldShare = max(0.01, $path['keep']);
                $batches = max(1, (int) ceil(($shortfall / $yieldShare) / $target - 0.0001));
                $volume = $batches * $target;
                $pitchBy = date('Y-m-d', strtotime($weeks[$w] . ' -' . $path['days'] . ' days'));
                $production[] = ['product_id' => $productId, 'recipe_version_id' => $recipeId, 'week' => $w, 'needed_by' => $weeks[$w], 'shortfall_l' => $shortfall,
                                 'batches' => $batches, 'volume_l' => $volume, 'yield_l' => $volume * $yieldShare, 'pitch_by' => $pitchBy, 'late' => $pitchBy < $in['today'],
                                 'lead_days' => $path['days'], 'missing_durations' => $path['missing']];
                $planned[$w] += $volume * $yieldShare;
                $available += $volume * $yieldShare;
            }
        }
        if ($recipe === null && array_sum($short) > 0) {
            $warnings[] = 'Product ' . ($in['configs'][array_key_first(array_filter($in['configs'], static fn($c) => (int) $c['product_id'] === $productId))]['product_name'] ?? $productId) . ' has no active recipe, so no batches can be suggested.';
        }
        if ($path !== null && $path['missing'] > 0 && array_sum($short) > 0) {
            $warnings[] = 'The active recipe of product ' . $productId . ' has ' . $path['missing'] . ' stages without a duration, so pitch-by dates may be late.';
        }
        $bulk[$productId] = ['need' => $need, 'supply' => $bulkSupply[$productId] ?? $zero, 'short' => $short, 'planned' => $planned];
    }

    // (3) Materials: needs by item and week, netted against released stock and open purchase orders.
    $needs = [];
    $addNeed = static function (int $itemId, int $w, float $qty, string $source) use (&$needs, $zero) {
        if ($qty <= 0) {
            return;
        }
        $needs[$itemId] ??= ['qty' => $zero, 'sources' => []];
        $needs[$itemId]['qty'][$w] += $qty;
        $needs[$itemId]['sources'][$source] = true;
    };
    foreach ($packaging as $configId => $p) {
        foreach ($in['configs'][$configId]['bom'] as $itemId => $perUnit) {
            foreach ($p['to_package'] as $w => $units) {
                $addNeed($itemId, $w, $units * $perUnit, 'packaging');
            }
        }
    }
    foreach ($in['draft_runs'] as $run) {
        $w = planning_week_index($weeks, $run['run_on']);
        if ($w === null) {
            continue;
        }
        foreach ($in['configs'][(int) $run['packaging_configuration_id']]['bom'] ?? [] as $itemId => $perUnit) {
            $addNeed($itemId, $w, (float) $run['units'] * $perUnit, 'draft packaging runs');
        }
    }
    $recipeNeeds = static function (array $recipe, float $volume, int $batches, int $w, string $source) use ($addNeed) {
        foreach ($recipe['lines'] as $line) {
            $qty = $line['qty_per_batch_base'] !== null ? (float) $line['qty_per_batch_base'] * $batches : (float) $line['qty_per_l'] * $volume;
            $addNeed((int) $line['item_id'], $w, $qty, $source);
        }
    };
    foreach ($production as $suggestion) {
        $recipeNeeds($in['recipes'][$suggestion['recipe_version_id']], $suggestion['volume_l'], $suggestion['batches'], planning_week_index($weeks, $suggestion['pitch_by']) ?? 0, 'suggested batches');
    }
    foreach ($in['orders'] as $order) {
        $recipe = $in['recipes'][(int) $order['recipe_version_id']] ?? null;
        $w = planning_week_index($weeks, $order['planned_pitch_on'] ?? $in['today']);
        if ($recipe !== null && $w !== null) {
            $recipeNeeds($recipe, (float) $order['planned_volume_l'], 1, $w, 'production orders');
        }
    }
    $incoming = [];
    foreach ($in['receipts'] as $line) {
        $w = planning_week_index($weeks, $line['expected_on'] ?? $in['today']);
        if ($w !== null) {
            $incoming[(int) $line['item_id']] ??= $zero;
            $incoming[(int) $line['item_id']][$w] += (float) $line['qty_outstanding_base'];
        }
    }
    $materials = [];
    $purchasing = [];
    foreach ($needs as $itemId => $need) {
        $available = $in['stock'][$itemId] ?? 0.0;
        $short = $zero;
        for ($w = 0; $w < $n; $w++) {
            $available += $incoming[$itemId][$w] ?? 0.0;
            $available -= $need['qty'][$w];
            if ($available < -0.0001) {
                $short[$w] = -$available;
                $available = 0.0;
            }
        }
        $item = $in['items'][$itemId];
        $materials[$itemId] = ['item' => $item, 'need' => $need['qty'], 'sources' => array_keys($need['sources']), 'on_hand' => $in['stock'][$itemId] ?? 0.0,
                               'on_order' => array_sum($incoming[$itemId] ?? []), 'short' => $short];
        $totalShort = array_sum($short);
        if ($totalShort <= 0) {
            continue;
        }
        $first = (int) array_key_first(array_filter($short, static fn($q) => $q > 0));
        $supplier = $in['suppliers'][$itemId] ?? null;
        $lead = $supplier['lead_time_days'] ?? null;
        $orderBy = $lead === null ? null : date('Y-m-d', strtotime($weeks[$first] . ' -' . (int) $lead . ' days'));
        $purchaseQty = $supplier && (float) $supplier['to_base_factor'] > 0 ? ceil($totalShort / (float) $supplier['to_base_factor'] - 0.0001) : null;
        $purchasing[$itemId] = ['item' => $item, 'week' => $first, 'needed_by' => $weeks[$first], 'short_first' => $short[$first], 'short_total' => $totalShort,
                                'supplier' => $supplier, 'order_by' => $orderBy, 'late' => $orderBy !== null && $orderBy < $in['today'], 'purchase_qty' => $purchaseQty,
                                'sources' => array_keys($need['sources']),
                                'fruit_kg' => $item['item_class'] === 'juice' && $in['juice_l_per_kg'] ? $totalShort / $in['juice_l_per_kg'] : null];
        if ($supplier === null) {
            $warnings[] = $item['code'] . ' is short but has no active supplier.';
        } elseif ($lead === null) {
            $warnings[] = $item['code'] . ': ' . $supplier['supplier_name'] . ' has no lead time, so the order-by date is unknown.';
        }
    }
    uasort($purchasing, static fn($a, $b) => [$a['order_by'] ?? $a['needed_by'], $a['item']['code']] <=> [$b['order_by'] ?? $b['needed_by'], $b['item']['code']]);
    usort($production, static fn($a, $b) => [$a['pitch_by'], $a['product_id']] <=> [$b['pitch_by'], $b['product_id']]);
    return ['level' => $level, 'weeks' => $weeks, 'demand_by_type' => $byType, 'value_by_type' => $valueByType, 'packaging' => $packaging, 'bulk' => $bulk,
            'bulk_supply_rows' => $supplyRows, 'production' => $production, 'materials' => $materials, 'purchasing' => $purchasing, 'warnings' => array_values(array_unique($warnings))];
}

/**
 * The projection at the chosen level, with each suggestion marked by what drives it: planned when production orders
 * and draft runs need it with no customer demand at all, firm when the firm-only projection needs it, standing when
 * firm + standing does, else forecast.
 */
function planning_projection(PDO $pdo, string $level, int $weeks = PLANNING_DEFAULT_WEEKS): array
{
    $level = isset(PLANNING_LEVELS[$level]) ? $level : 'standing';
    $inputs = planning_inputs($pdo, $weeks);
    $runs = ['planned' => planning_run($inputs, 'planned')];
    foreach (array_keys(PLANNING_LEVELS) as $l) {
        $runs[$l] = planning_run($inputs, $l);
        if ($l === $level) {
            break;
        }
    }
    $result = $runs[$level];
    $driver = static function (callable $has) use ($runs, $level): string {
        foreach (['planned', ...array_keys(PLANNING_LEVELS)] as $l) {
            if ($has($runs[$l])) {
                return $l === 'all' ? 'forecast' : $l;
            }
            if ($l === $level) {
                break;
            }
        }
        return $level === 'all' ? 'forecast' : $level;
    };
    foreach ($result['production'] as $i => $s) {
        $result['production'][$i]['driver'] = $driver(static fn($r) => (bool) array_filter($r['production'], static fn($x) => $x['product_id'] === $s['product_id'] && $x['week'] <= $s['week']));
    }
    foreach ($result['purchasing'] as $itemId => $s) {
        $result['purchasing'][$itemId]['driver'] = $driver(static fn($r) => isset($r['purchasing'][$itemId]));
    }
    $result['configs'] = $inputs['configs'];
    $result['juice_l_per_kg'] = $inputs['juice_l_per_kg'];
    $result['products'] = array_column(array_map(static fn($c) => ['id' => (int) $c['product_id'], 'name' => $c['product_name']], $inputs['configs']), 'name', 'id');
    return $result;
}

/** Dashboard figures from the firm-and-standing projection: batches to pitch and items to order by the end of next week. */
function planning_dashboard_stats(PDO $pdo): array
{
    $p = planning_projection($pdo, 'standing');
    $cutoff = date('Y-m-d', strtotime(forecast_week_start() . ' +13 days'));
    $batches = array_filter($p['production'], static fn($x) => $x['pitch_by'] <= $cutoff);
    $items = array_filter($p['purchasing'], static fn($x) => ($x['order_by'] ?? $x['needed_by']) <= $cutoff);
    return ['batches' => array_sum(array_column($batches, 'batches')), 'batches_late' => count(array_filter($batches, static fn($x) => $x['late'])),
            'items' => count($items), 'items_late' => count(array_filter($items, static fn($x) => $x['late'])),
            'items_no_lead' => count(array_filter($items, static fn($x) => $x['order_by'] === null))];
}
