<?php
declare(strict_types=1);

// Fulfilling customer orders: what each open line still needs packaged, draft packaging runs
// created for order lines, draft shipments (removals) created from an order, the links that
// keep runs and shipments tied to order lines, and the order status roll-up.
// Posting is unchanged: packaging runs post on their screen (production), removals on theirs (compliance).

require_once __DIR__ . '/queries.php';
require_once __DIR__ . '/../packaging-runs/queries.php';
require_once __DIR__ . '/../removals/queries.php';

/**
 * Every open order line of a format, oldest due first, with what it still needs packaged.
 * Released finished stock is promised in due-date order (then order number): a line's
 * stock_covered is the part of its open units that stock on hand covers after earlier lines;
 * need = open − in packaging runs − stock_covered.
 */
function orders_format_needs(PDO $pdo, int $configurationId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT l.id, l.sales_order_id, l.order_number, l.customer_name, l.requested_on, l.line_no, l.units_open, l.units_in_packaging_runs,
               COALESCE((SELECT SUM(prol.units) FROM app.packaging_run_order_lines prol JOIN app.packaging_runs pr ON pr.id = prol.packaging_run_id
                          WHERE prol.sales_order_line_id = l.id AND pr.status = 'posted'), 0) AS units_packaged
        FROM app.v_sales_order_lines l
        WHERE l.packaging_configuration_id = :c AND l.units_open > 0
        ORDER BY l.requested_on, l.order_number, l.line_no
    SQL);
    $statement->execute(['c' => $configurationId]);
    $available = orders_released_units($pdo, $configurationId);
    $out = [];
    foreach ($statement->fetchAll() as $row) {
        // Units from this line's own posted runs are already in stock; units in draft runs are still to come.
        $inDraftRuns = max(0, (int) $row['units_in_packaging_runs'] - (int) $row['units_packaged']);
        $uncovered = max(0, (int) $row['units_open'] - $inDraftRuns);
        $covered = min($available, $uncovered);
        $available -= $covered;
        $row['units_in_draft_runs'] = $inDraftRuns;
        $row['stock_covered'] = $covered;
        $row['need'] = $uncovered - $covered;
        $out[(int) $row['id']] = $row;
    }
    return $out;
}

/** Released finished units available of a format (all locations). */
function orders_released_units(PDO $pdo, int $configurationId): int
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT COALESCE(SUM(fs.units_available), 0) FROM app.v_finished_stock fs JOIN app.lots l ON l.id = fs.lot_id
        WHERE fs.packaging_configuration_id = :c AND l.quality_status = 'released'
    SQL);
    $statement->execute(['c' => $configurationId]);
    return (int) $statement->fetchColumn();
}

/** Formats with open order lines: config id => [name, product, units_open, need] (the packaging queue). */
function orders_formats_with_open_lines(PDO $pdo): array
{
    $rows = $pdo->query(<<<'SQL'
        SELECT l.packaging_configuration_id AS id, l.configuration_name, l.product_name, l.package_kind, l.units_per_case,
               SUM(l.units_open) AS units_open, MIN(l.requested_on) AS first_due
        FROM app.v_sales_order_lines l WHERE l.units_open > 0
        GROUP BY 1, 2, 3, 4, 5 ORDER BY MIN(l.requested_on), 3, 2
    SQL)->fetchAll();
    $out = [];
    foreach ($rows as $row) {
        $out[(int) $row['id']] = $row;
    }
    return $out;
}

/**
 * The packaging plan for a set of order lines, grouped by format. $lineFilter: null = every open line of
 * $configurationIds; otherwise only these line ids. Each group: the configuration, its lines with need,
 * the units to package (sum of need), the volume that takes, candidate batches and output locations.
 */
function orders_package_plan(PDO $pdo, array $configurationIds, ?array $lineFilter = null): array
{
    $groups = [];
    foreach (array_unique(array_map('intval', $configurationIds)) as $configId) {
        $config = find_packaging_configuration($pdo, $configId);
        if ($config === null) {
            continue;
        }
        $lines = orders_format_needs($pdo, $configId);
        if ($lineFilter !== null) {
            $lines = array_intersect_key($lines, array_flip(array_map('intval', $lineFilter)));
        }
        if ($lines === []) {
            continue;
        }
        $need = array_sum(array_column($lines, 'need'));
        $batches = orders_candidate_batches($pdo, (int) $config['product_id']);
        $groups[$configId] = [
            'config' => $config, 'lines' => $lines, 'need' => $need,
            'volume_l' => orders_volume_for_units($config, $need),
            'batches' => $batches,
            'default_batch_id' => orders_default_batch($batches, orders_volume_for_units($config, $need)),
        ];
    }
    return $groups;
}

/** Liters to take from the batch for a number of units: fill ÷ (1 − the format's expected loss). */
function orders_volume_for_units(array $config, int $units): float
{
    $loss = min(99.0, max(0.0, (float) $config['expected_loss_pct']));
    return round($units * (float) $config['fill_volume_l'] / (1 - $loss / 100), 3);
}

/** Active batches of a product at a packageable stage with volume, oldest first, with release state and vessels. */
function orders_candidate_batches(PDO $pdo, int $productId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT b.id FROM app.batches b
        WHERE b.product_id = :p AND b.status = 'active' AND b.current_stage_code = ANY(string_to_array(:stages, ',')) AND b.current_volume_l > 0
        ORDER BY b.started_at, b.id
    SQL);
    $statement->execute(['p' => $productId, 'stages' => implode(',', PACKAGING_BATCH_STAGES)]);
    $out = [];
    foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $batchId) {
        $batch = find_packaging_batch($pdo, (int) $batchId);
        if ($batch === null) {
            continue;
        }
        $batch['released'] = packaging_release_gate($pdo, $batch);
        $batch['vessels'] = find_packaging_batch_vessels($pdo, (int) $batchId);
        $batch['locations'] = packaging_output_location_options($pdo, (int) $batch['premises_id']);
        $out[(int) $batchId] = $batch;
    }
    return $out;
}

/** Oldest released batch with a vessel holding enough; else the oldest with enough; else the oldest. */
function orders_default_batch(array $batches, float $volumeL): ?int
{
    $fits = static fn(array $b) => max(array_map(static fn($v) => (float) $v['volume_l'], $b['vessels']) ?: [0]) + 0.0005 >= $volumeL;
    foreach ([static fn($b) => $b['released'] && $fits($b), $fits, static fn($b) => true] as $test) {
        foreach ($batches as $id => $batch) {
            if ($test($batch)) {
                return $id;
            }
        }
    }
    return null;
}

/**
 * Create one draft packaging run per group. $input: config id => [units, batch_id, source_vessel_id, output_location_id, run_on].
 * Units go to the group's lines in due order up to each line's need. Returns [runs created, errors by config id].
 * Caller owns the transaction and logs per run and per order.
 */
function orders_create_packaging_runs(PDO $pdo, array $plan, array $input, int $userId): array
{
    $errors = [];
    $runs = [];
    foreach ($plan as $configId => $group) {
        $raw = $input[$configId] ?? [];
        $units = filter_var($raw['units'] ?? '', FILTER_VALIDATE_INT);
        if ($units === false || $units < 0) {
            $errors[$configId] = 'Enter whole units, zero to skip this format.';
            continue;
        }
        if ($units === 0) {
            continue;
        }
        $batch = $group['batches'][(int) ($raw['batch_id'] ?? 0)] ?? null;
        if ($batch === null) {
            $errors[$configId] = 'Choose a batch of ' . $group['config']['name'] . ' that is ready to package.';
            continue;
        }
        $volumeL = orders_volume_for_units($group['config'], $units);
        $vessel = null;
        foreach ($batch['vessels'] as $candidate) {
            if ((int) $candidate['vessel_id'] === (int) ($raw['source_vessel_id'] ?? 0)) { $vessel = $candidate; }
        }
        if ($vessel === null) {
            $errors[$configId] = 'Choose a vessel that batch ' . $batch['number'] . ' is in.';
            continue;
        }
        if ($volumeL > (float) $vessel['volume_l'] + 0.0005) {
            $errors[$configId] = $units . ' units take ' . fmt_qty($volumeL, 'L') . '; ' . $vessel['vessel_name'] . ' holds ' . fmt_qty($vessel['volume_l'], 'L') . '.';
            continue;
        }
        $locationId = (int) ($raw['output_location_id'] ?? 0);
        if (!isset($batch['locations'][$locationId])) {
            $errors[$configId] = 'Choose a packaged goods location.';
            continue;
        }
        $runOn = (string) ($raw['run_on'] ?? '');
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $runOn);
        if ($date === false || $date->format('Y-m-d') !== $runOn) {
            $errors[$configId] = 'Enter the run date.';
            continue;
        }
        // Split the units across the lines in due order, up to each line's need.
        $remaining = $units;
        $links = [];
        foreach ($group['lines'] as $lineId => $line) {
            $take = min($remaining, (int) $line['need']);
            if ($take > 0) {
                $links[$lineId] = $take;
                $remaining -= $take;
            }
        }
        $orderNumbers = array_values(array_unique(array_map(static fn($id) => $group['lines'][$id]['order_number'], array_keys($links))));
        $run = insert_packaging_run($pdo, (int) $batch['premises_id'], (int) $batch['id'], (int) $configId, (int) $vessel['vessel_id'], $locationId, $runOn,
            $volumeL, $units, null, null, null, null, $orderNumbers === [] ? 'Packaged for stock from the order screen.' : 'For ' . implode(', ', $orderNumbers) . '.', $userId);
        // Backflushed materials at the bill of materials; explicit ones are chosen by lot on the run before posting.
        $materials = [];
        foreach (find_bom_for_configuration($pdo, (int) $configId) as $bom) {
            if ($bom['consumption_mode'] === 'backflush') {
                $materials[] = ['item_id' => (int) $bom['item_id'], 'qty_base' => round($units * (float) $bom['qty_per_unit_base'], 4), 'lot_id' => null, 'mode' => 'backflush'];
            }
        }
        replace_packaging_run_materials($pdo, (int) $run['id'], $materials);
        $link = $pdo->prepare('INSERT INTO app.packaging_run_order_lines (packaging_run_id, sales_order_line_id, units) VALUES (:r, :l, :u)');
        foreach ($links as $lineId => $take) {
            $link->execute(['r' => $run['id'], 'l' => $lineId, 'u' => $take]);
        }
        $runs[] = $run + ['configuration_name' => $group['config']['name'], 'batch_number' => $batch['number'], 'links' => $links,
                          'order_ids' => array_values(array_unique(array_map(static fn($id) => (int) $group['lines'][$id]['sales_order_id'], array_keys($links))))];
    }
    return [$runs, $errors];
}

/**
 * Create a draft shipment (removal) for an order's unshipped units: from the bonded location holding the most of
 * them, lots first-expiring first (FEFO by packaging date), kegs filled with the lot. Ships what is on hand; the
 * rest stays open. Returns [removal, lines shipped (line id => units), short (line id => units)].
 * Caller owns the transaction and the log.
 */
function orders_ship(PDO $pdo, array $order, int $userId): array
{
    if (!in_array($order['status'], ['confirmed', 'in_fulfillment'], true)) {
        throw new RuntimeException('Only a confirmed or in-fulfillment order can be shipped.');
    }
    $draft = $pdo->prepare("SELECT number FROM app.removals WHERE sales_order_id = :id AND status = 'draft' LIMIT 1");
    $draft->execute(['id' => $order['id']]);
    if (($number = $draft->fetchColumn()) !== false) {
        throw new RuntimeException('Shipment ' . $number . ' is already drafted for this order; post or delete it first.');
    }
    $toShip = [];
    foreach (find_order_lines($pdo, (int) $order['id']) as $line) {
        if ($line['line_status'] === 'open' && (int) $line['units_open'] > 0) {
            $toShip[(int) $line['id']] = $line;
        }
    }
    if ($toShip === []) {
        throw new RuntimeException('Nothing is left to ship on this order.');
    }
    $destination = (string) $order['destination_kind'];
    $customerId = in_array($destination, REMOVAL_CUSTOMER_REQUIRED, true) ? (int) $order['customer_id'] : null;
    if ($destination === 'in_bond_transfer') {
        $customer = find_customer($pdo, (int) $order['customer_id']);
        if (trim((string) ($customer['permit_number'] ?? '')) === '') {
            throw new RuntimeException($order['customer_name'] . ' has no permit number; an in-bond transfer needs the consignee\'s permit.');
        }
    }
    $toLocationId = null;
    if ($destination === 'taproom_transfer') {
        $taprooms = array_keys(array_filter(removals_location_options($pdo, 'taproom'), static fn($id) => removals_location_premises($pdo, (int) $id) === (int) $order['premises_id'], ARRAY_FILTER_USE_KEY));
        $toLocationId = $taprooms[0] ?? throw new RuntimeException('There is no tax-paid taproom location to transfer to.');
    }
    // Stock of the order's formats by bonded location: pick the location that covers the most units.
    $stock = $pdo->prepare(<<<'SQL'
        SELECT fs.location_id, fs.lot_id, fs.lot_number, fs.packaging_configuration_id, fs.package_kind, fs.units_available, fs.packaged_on
        FROM app.v_finished_stock fs JOIN app.lots l ON l.id = fs.lot_id JOIN app.locations loc ON loc.id = fs.location_id
        WHERE fs.packaging_configuration_id = ANY (CAST(:configs AS bigint[])) AND l.quality_status = 'released' AND fs.units_available > 0
          AND loc.premises_id = :p AND loc.kind IN ('packaged_goods', 'taproom') AND loc.tax_state = 'bonded' AND loc.active
        ORDER BY fs.packaged_on, fs.lot_number
    SQL);
    $stock->execute(['configs' => '{' . implode(',', array_unique(array_map(static fn($l) => (int) $l['packaging_configuration_id'], $toShip))) . '}', 'p' => $order['premises_id']]);
    $byLocation = [];
    foreach ($stock->fetchAll() as $row) {
        if ($row['package_kind'] === 'keg') {
            // Keg lots ship only in kegs filled with the lot.
            $row['kegs'] = find_kegs_for_lot($pdo, (int) $row['lot_id'], null);
            $row['units_available'] = min((int) $row['units_available'], count($row['kegs']));
        }
        $byLocation[(int) $row['location_id']][] = $row;
    }
    $best = null;
    $bestPlan = [];
    foreach ($byLocation as $locationId => $lots) {
        $plan = orders_allocate_lots($toShip, $lots);
        if ($best === null || array_sum(array_column($plan, 'units')) > array_sum(array_column($bestPlan, 'units'))) {
            $best = $locationId;
            $bestPlan = $plan;
        }
    }
    if ($best === null || $bestPlan === []) {
        throw new RuntimeException('Nothing on hand can ship for this order yet; package it first.');
    }
    $removal = insert_removal($pdo, (int) $order['premises_id'], 'out', $destination, $customerId, $best, $toLocationId, (new DateTimeImmutable())->format(DATE_ATOM),
        $order['customer_reference'] ?: $order['number'], 'Shipment for order ' . $order['number'] . '.', $userId);
    $pdo->prepare('UPDATE app.removals SET sales_order_id = :o WHERE id = :id')->execute(['o' => $order['id'], 'id' => $removal['id']]);
    replace_removal_lines($pdo, (int) $removal['id'], array_map(static fn($p) => ['lot_id' => $p['lot_id'], 'units' => $p['units'], 'keg_ids' => $p['keg_ids']], $bestPlan));
    orders_link_removal_lines($pdo, (int) $removal['id']);
    $shipped = [];
    foreach ($bestPlan as $p) {
        $shipped[$p['line_id']] = ($shipped[$p['line_id']] ?? 0) + $p['units'];
    }
    $short = [];
    foreach ($toShip as $lineId => $line) {
        $left = (int) $line['units_open'] - ($shipped[$lineId] ?? 0);
        if ($left > 0) { $short[$lineId] = $left; }
    }
    return [$removal, $shipped, $short];
}

/** FEFO allocation of lots (one location) to order lines: [[line_id, lot_id, units, keg_ids], ...]. */
function orders_allocate_lots(array $lines, array $lots): array
{
    $plan = [];
    foreach ($lines as $lineId => $line) {
        $left = (int) $line['units_open'];
        foreach ($lots as $i => $lot) {
            if ($left <= 0) { break; }
            if ((int) $lot['packaging_configuration_id'] !== (int) $line['packaging_configuration_id'] || (int) $lot['units_available'] <= 0) { continue; }
            $take = min($left, (int) $lot['units_available']);
            $kegIds = $lot['package_kind'] === 'keg' ? array_slice(array_keys($lot['kegs']), 0, $take) : [];
            if ($lot['package_kind'] === 'keg') {
                $lots[$i]['kegs'] = array_slice($lot['kegs'], $take, null, true);
            }
            $lots[$i]['units_available'] = (int) $lot['units_available'] - $take;
            $plan[] = ['line_id' => $lineId, 'lot_id' => (int) $lot['lot_id'], 'units' => $take, 'keg_ids' => $kegIds];
            $left -= $take;
        }
    }
    return $plan;
}

/**
 * Tie a removal's lines to its order's lines by format: each removal line (lot) goes to the order's open line of the
 * same format, oldest line first, up to what that line still has open. Runs after every save of an order's removal.
 */
function orders_link_removal_lines(PDO $pdo, int $removalId): void
{
    $removal = $pdo->prepare('SELECT sales_order_id, direction FROM app.removals WHERE id = :id');
    $removal->execute(['id' => $removalId]);
    $row = $removal->fetch();
    if ($row === false || $row['sales_order_id'] === null) {
        return;
    }
    $pdo->prepare('UPDATE app.removal_lines SET sales_order_line_id = NULL WHERE removal_id = :id')->execute(['id' => $removalId]);
    $open = [];
    foreach (find_order_lines($pdo, (int) $row['sales_order_id']) as $line) {
        if ($line['line_status'] !== 'cancelled') {
            $open[(int) $line['id']] = ['config' => (int) $line['packaging_configuration_id'], 'left' => max(0, (int) $line['units_open'])];
        }
    }
    $rows = $pdo->prepare(<<<'SQL'
        SELECT rl.id, rl.units, fl.packaging_configuration_id FROM app.removal_lines rl JOIN app.finished_lots fl ON fl.lot_id = rl.lot_id
        WHERE rl.removal_id = :id ORDER BY rl.id
    SQL);
    $rows->execute(['id' => $removalId]);
    $set = $pdo->prepare('UPDATE app.removal_lines SET sales_order_line_id = :l WHERE id = :id');
    foreach ($rows->fetchAll() as $removalLine) {
        $target = null;
        foreach ($open as $lineId => $line) {
            if ($line['config'] === (int) $removalLine['packaging_configuration_id'] && ($line['left'] > 0 || $target === null)) {
                $target = $lineId;
                if ($line['left'] > 0) { break; }
            }
        }
        if ($target !== null) {
            $set->execute(['l' => $target, 'id' => $removalLine['id']]);
            $open[$target]['left'] -= (int) $removalLine['units'];
        }
    }
}

/** Orders a removal or packaging run is tied to (call before deleting it). */
function orders_for_removal(PDO $pdo, int $removalId): array
{
    $statement = $pdo->prepare('SELECT sales_order_id FROM app.removals WHERE id = :id AND sales_order_id IS NOT NULL');
    $statement->execute(['id' => $removalId]);
    return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
}

function orders_for_packaging_run(PDO $pdo, int $runId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT DISTINCT ol.sales_order_id FROM app.packaging_run_order_lines prol JOIN app.sales_order_lines ol ON ol.id = prol.sales_order_line_id
        WHERE prol.packaging_run_id = :id
    SQL);
    $statement->execute(['id' => $runId]);
    return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Roll an order's status up from its lines: shipped when every open line has shipped in full, in fulfillment once
 * anything is shipped, packaging or drafted for shipping, confirmed otherwise. Draft, closed and cancelled orders
 * are left alone. Logs order_status_changed when it changes. Returns the new status.
 */
function order_refresh_status(PDO $pdo, int $orderId, string $screen): ?string
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT so.status, so.number,
               bool_and(l.units_shipped >= l.units_ordered) FILTER (WHERE l.line_status = 'open') AS all_shipped,
               COALESCE(SUM(l.units_shipped), 0) + COALESCE(SUM(l.units_in_packaging_runs), 0) AS activity,
               EXISTS (SELECT 1 FROM app.removals r WHERE r.sales_order_id = so.id AND r.status = 'draft') AS draft_shipment
        FROM app.sales_orders so LEFT JOIN app.v_sales_order_lines l ON l.sales_order_id = so.id
        WHERE so.id = :id GROUP BY so.id
    SQL);
    $statement->execute(['id' => $orderId]);
    $row = $statement->fetch();
    if ($row === false || !in_array($row['status'], ['confirmed', 'in_fulfillment', 'shipped'], true)) {
        return $row['status'] ?? null;
    }
    $status = $row['all_shipped'] ? 'shipped' : ((int) $row['activity'] > 0 || $row['draft_shipment'] ? 'in_fulfillment' : 'confirmed');
    if ($status !== $row['status']) {
        $pdo->prepare('UPDATE app.sales_orders SET status = :s WHERE id = :id')->execute(['s' => $status, 'id' => $orderId]);
        log_activity($pdo, 'order_status_changed', 'sales_order', $orderId, $row['number'], ['status' => $row['status']], ['status' => $status], [], $screen);
    }
    return $status;
}

/** Refresh every listed order (ids may repeat). */
function orders_refresh_statuses(PDO $pdo, array $orderIds, string $screen): void
{
    foreach (array_unique(array_map('intval', $orderIds)) as $orderId) {
        order_refresh_status($pdo, $orderId, $screen);
    }
}

/**
 * Dashboard figures: confirmed orders due by the end of this week (and overdue), and orders due in the next
 * 14 days with units still to package (not covered by stock on hand or draft runs).
 */
function orders_dashboard_stats(PDO $pdo): array
{
    $row = $pdo->query(<<<'SQL'
        SELECT count(*) FILTER (WHERE requested_on <= date_trunc('week', current_date)::date + 6) AS due_this_week,
               count(*) FILTER (WHERE requested_on < current_date) AS overdue
        FROM app.sales_orders WHERE status IN ('confirmed', 'in_fulfillment')
    SQL)->fetch();
    $atRisk = [];
    $horizon = date('Y-m-d', strtotime('+14 days'));
    foreach (array_keys(orders_formats_with_open_lines($pdo)) as $configId) {
        foreach (orders_format_needs($pdo, $configId) as $line) {
            if ($line['need'] > 0 && $line['requested_on'] <= $horizon) {
                $atRisk[(int) $line['sales_order_id']] = true;
            }
        }
    }
    return ['due_this_week' => (int) $row['due_this_week'], 'overdue' => (int) $row['overdue'], 'at_risk' => count($atRisk)];
}
