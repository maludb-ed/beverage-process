<?php
declare(strict_types=1);

// Press runs: fruit lots in, juice lots (into vessels) and pomace lots out. Posting copies
// the receipts exemplar: lock, validate, write lots + ledger rows + occupancies, mark posted.

require_once __DIR__ . '/../lots/queries.php';
require_once __DIR__ . '/../inventory/ledger.php';
require_once __DIR__ . '/../batches/queries.php';
require_once __DIR__ . '/../premises/queries.php';

const PRESS_RUN_STATUSES = ['draft' => 'Draft', 'posted' => 'Posted', 'cancelled' => 'Cancelled'];
const PRESS_RUN_SORTS = ['number' => 'pr.number', 'run_on' => 'pr.run_on', 'status' => 'pr.status'];
const PRESS_RUN_OUTPUT_KINDS = ['juice' => 'Juice', 'pomace' => 'Pomace'];

function find_press_runs(PDO $pdo, string $search = '', string $sort = '-run_on', int $page = 1, ?string $status = null): array
{
    $where = [];
    $params = [];
    if ($search !== '') { $where[] = 'pr.number ILIKE :s'; $params['s'] = '%' . $search . '%'; }
    if ($status) { $where[] = 'pr.status = :status'; $params['status'] = $status; }
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    $from = ' FROM app.press_runs pr LEFT JOIN app.vessels v ON v.id = pr.press_vessel_id';
    return paged_query($pdo,
        'SELECT pr.id, pr.number, pr.run_on, pr.status, pr.fruit_kg_total, pr.juice_l_total, pr.yield_l_per_kg, v.name AS press_name,
                (SELECT sum(i.qty_kg) FROM app.press_run_inputs i WHERE i.press_run_id = pr.id) AS draft_fruit_kg,
                (SELECT sum(o.qty_base) FROM app.press_run_outputs o WHERE o.press_run_id = pr.id AND o.kind = \'juice\') AS draft_juice_l'
            . $from . $whereSql . ' ORDER BY ' . order_by($sort, PRESS_RUN_SORTS, '-run_on') . ', pr.id DESC',
        'SELECT count(*)' . $from . $whereSql, $params, $page);
}

function count_press_runs(PDO $pdo, string $search = ''): int
{
    $statement = $pdo->prepare('SELECT count(*) FROM app.press_runs pr WHERE (:s = \'\' OR pr.number ILIKE :like)');
    $statement->execute(['s' => $search, 'like' => '%' . $search . '%']);
    return (int) $statement->fetchColumn();
}

function find_press_run(PDO $pdo, int $id, bool $lock = false): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT pr.*, v.name AS press_name, p.name AS premises_name, uc.display_name AS created_by_name, up.display_name AS posted_by_name
        FROM app.press_runs pr
        JOIN app.premises p ON p.id = pr.premises_id
        LEFT JOIN app.vessels v ON v.id = pr.press_vessel_id
        LEFT JOIN app.users uc ON uc.id = pr.created_by
        LEFT JOIN app.users up ON up.id = pr.posted_by
        WHERE pr.id = :id
    SQL . ($lock ? ' FOR UPDATE OF pr' : ''));
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function find_press_run_inputs(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT pi.*, l.lot_number, l.item_id, l.unit_cost_base, l.quality_status, i.code AS item_code, i.name AS item_name,
               (SELECT a.value_text FROM app.lot_attributes a WHERE a.lot_id = l.id AND a.key = 'variety') AS variety
        FROM app.press_run_inputs pi JOIN app.lots l ON l.id = pi.lot_id JOIN app.items i ON i.id = l.item_id
        WHERE pi.press_run_id = :id ORDER BY pi.id
    SQL);
    $statement->execute(['id' => $id]);
    return $statement->fetchAll();
}

function find_press_run_outputs(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT o.*, i.code AS item_code, i.name AS item_name, i.base_unit_code, l.lot_number, v.name AS vessel_name, loc.name AS location_name
        FROM app.press_run_outputs o
        JOIN app.items i ON i.id = o.item_id
        LEFT JOIN app.lots l ON l.id = o.lot_id
        LEFT JOIN app.vessels v ON v.id = o.vessel_id
        LEFT JOIN app.locations loc ON loc.id = o.location_id
        WHERE o.press_run_id = :id ORDER BY (o.kind = 'juice') DESC, o.id
    SQL);
    $statement->execute(['id' => $id]);
    return $statement->fetchAll();
}

/** Released fruit lots with stock: lot_id => row with on_hand (kg) and label "lot · item · variety · 1,234.5 lb". */
function find_fruit_lot_options(PDO $pdo): array
{
    $statement = $pdo->query(<<<'SQL'
        SELECT b.lot_id, b.lot_number, b.item_id, b.item_code, b.item_name, b.unit_cost_base, sum(b.qty_on_hand) AS on_hand,
               (SELECT a.value_text FROM app.lot_attributes a WHERE a.lot_id = b.lot_id AND a.key = 'variety') AS variety
        FROM app.v_lot_balances b
        WHERE b.item_class = 'fruit' AND b.quality_status = 'released' AND b.qty_on_hand > 0
        GROUP BY b.lot_id, b.lot_number, b.item_id, b.item_code, b.item_name, b.unit_cost_base
        ORDER BY b.lot_number
    SQL);
    $rows = [];
    foreach ($statement->fetchAll() as $row) {
        $row['label'] = $row['lot_number'] . ' · ' . $row['item_code'] . ' · ' . ($row['variety'] ?? '—') . ' · ' . fmt_qty($row['on_hand'], 'kg', 1, 'fruit');
        $rows[(int) $row['lot_id']] = $row;
    }
    return $rows;
}

/** The balance location holding most of a lot: ['location_id', 'qty_on_hand'] or null. */
function find_lot_primary_location(PDO $pdo, int $lotId): ?array
{
    $statement = $pdo->prepare('SELECT location_id, qty_on_hand FROM app.inventory_balances WHERE lot_id = :lot AND qty_on_hand > 0 ORDER BY qty_on_hand DESC, location_id LIMIT 1');
    $statement->execute(['lot' => $lotId]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** Output items by kind: ['juice' => [id => row], 'pomace' => [id => row]]. */
function press_run_output_items(PDO $pdo): array
{
    $items = ['juice' => [], 'pomace' => []];
    foreach ($pdo->query("SELECT id, code, name, item_class, base_unit_code FROM app.items WHERE active AND item_class IN ('juice', 'co_product') ORDER BY code") as $row) {
        $items[$row['item_class'] === 'juice' ? 'juice' : 'pomace'][(int) $row['id']] = $row;
    }
    return $items;
}

function press_vessel_options(PDO $pdo): array
{
    return $pdo->query("SELECT id, name FROM app.vessels WHERE active AND kind = 'press' ORDER BY name")->fetchAll(PDO::FETCH_KEY_PAIR);
}

function insert_press_run(PDO $pdo, int $premisesId, ?int $pressVesselId, string $runOn, ?string $startedAt, ?string $finishedAt, ?string $notes, int $createdBy): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.press_runs (number, premises_id, press_vessel_id, run_on, started_at, finished_at, notes, created_by)
        VALUES (app.next_number('press_run'), :premises, :press, :run_on, :started, :finished, :notes, :by)
        RETURNING id, number, status, premises_id, press_vessel_id, run_on, started_at, finished_at, notes
    SQL);
    $statement->execute(['premises' => $premisesId, 'press' => $pressVesselId, 'run_on' => $runOn, 'started' => $startedAt, 'finished' => $finishedAt, 'notes' => $notes, 'by' => $createdBy]);
    return $statement->fetch();
}

function update_press_run(PDO $pdo, int $id, int $premisesId, ?int $pressVesselId, string $runOn, ?string $startedAt, ?string $finishedAt, ?string $notes): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.press_runs SET premises_id = :premises, press_vessel_id = :press, run_on = :run_on, started_at = :started, finished_at = :finished, notes = :notes
        WHERE id = :id AND status = 'draft'
        RETURNING id, number, status, premises_id, press_vessel_id, run_on, started_at, finished_at, notes
    SQL);
    $statement->execute(['id' => $id, 'premises' => $premisesId, 'press' => $pressVesselId, 'run_on' => $runOn, 'started' => $startedAt, 'finished' => $finishedAt, 'notes' => $notes]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Only draft press runs can be edited.');
    }
    return $row;
}

/** Draft only. Inputs: [lot_id, qty_kg]; outputs: [kind, item_id, qty_base, brix, vessel_id, location_id]. */
function replace_press_run_lines(PDO $pdo, int $id, array $inputs, array $outputs): void
{
    $pdo->prepare('DELETE FROM app.press_run_inputs WHERE press_run_id = :id')->execute(['id' => $id]);
    $pdo->prepare('DELETE FROM app.press_run_outputs WHERE press_run_id = :id')->execute(['id' => $id]);
    $in = $pdo->prepare('INSERT INTO app.press_run_inputs (press_run_id, lot_id, qty_kg) VALUES (:id, :lot, :kg)');
    foreach ($inputs as $input) {
        $in->execute(['id' => $id, 'lot' => $input['lot_id'], 'kg' => round((float) $input['qty_kg'], 3)]);
    }
    $out = $pdo->prepare('INSERT INTO app.press_run_outputs (press_run_id, kind, item_id, qty_base, brix, vessel_id, location_id) VALUES (:id, :kind, :item, :qty, :brix, :vessel, :loc)');
    foreach ($outputs as $output) {
        $out->execute(['id' => $id, 'kind' => $output['kind'], 'item' => $output['item_id'], 'qty' => round((float) $output['qty_base'], 3), 'brix' => $output['brix'],
            'vessel' => $output['kind'] === 'juice' ? $output['vessel_id'] : null, 'loc' => $output['kind'] === 'pomace' ? $output['location_id'] : null]);
    }
}

function delete_press_run(PDO $pdo, int $id): bool
{
    $statement = $pdo->prepare("DELETE FROM app.press_runs WHERE id = :id AND status = 'draft'");
    $statement->execute(['id' => $id]);
    return $statement->rowCount() === 1;
}

/**
 * Post a locked draft run (spec steps 2 to 5): fruit issued, juice lots into vessels,
 * pomace lots to locations, totals. Caller owns the transaction and the activity row.
 * Returns ['totals' => [...], 'lots' => [...], 'capacity_warnings' => [...]].
 */
function post_press_run(PDO $pdo, int $id, int $userId): array
{
    $run = find_press_run($pdo, $id, true) ?? throw new RuntimeException('That press run does not exist.');
    if ($run['status'] !== 'draft') {
        throw new RuntimeException('Press run ' . $run['number'] . ' is already ' . $run['status'] . '.');
    }
    $inputs = find_press_run_inputs($pdo, $id);
    $outputs = find_press_run_outputs($pdo, $id);
    $juiceOutputs = array_values(array_filter($outputs, static fn($o) => $o['kind'] === 'juice'));
    if ($inputs === [] || $juiceOutputs === []) {
        throw new RuntimeException('Press run ' . $run['number'] . ' needs at least one fruit lot and one juice output.');
    }
    $at = $run['finished_at'] ?? (new DateTimeImmutable())->format(DATE_ATOM);
    $group = new_group_id();
    $line = 0;
    $fruitKg = 0.0;
    $fruitCost = 0.0;
    $varieties = [];
    foreach ($inputs as $input) {
        $location = find_lot_primary_location($pdo, (int) $input['lot_id']);
        if ($location === null) {
            throw new RuntimeException('Lot ' . $input['lot_number'] . ' has no stock left.');
        }
        $qty = (float) $input['qty_kg'];
        insert_inventory_transaction($pdo, $group, 'issue', (int) $input['item_id'], (int) $input['lot_id'], (int) $location['location_id'], (int) $run['premises_id'],
            -$qty, (float) $input['unit_cost_base'], 'press_run', $id, null, 'used_in_production', 'press_run', $id, 'press_run:' . $id . ':' . (++$line), $at, $userId);
        insert_consumption($pdo, null, $id, (int) $input['item_id'], (int) $input['lot_id'], $qty, 'fruit', 'press', null, $at, $userId, $group);
        $fruitKg += $qty;
        $fruitCost += $qty * (float) $input['unit_cost_base'];
        if ($input['variety'] !== null && $input['variety'] !== '') {
            $varieties[$input['variety']] = true;
        }
    }
    $juiceL = array_sum(array_map(static fn($o) => (float) $o['qty_base'], $juiceOutputs));
    $juiceCost = $juiceL > 0 ? $fruitCost / $juiceL : 0.0;
    $setLot = $pdo->prepare('UPDATE app.press_run_outputs SET lot_id = :lot WHERE id = :id');
    $lots = [];
    $warnings = [];
    $pomaceKg = 0.0;
    foreach ($outputs as $output) {
        $qty = (float) $output['qty_base'];
        if ($output['kind'] === 'juice') {
            $vessel = find_vessel($pdo, (int) $output['vessel_id'], true) ?? throw new RuntimeException('The juice vessel no longer exists.');
            if ($vessel['occupancy_id'] !== null) {
                throw new RuntimeException(batches_occupied_message($vessel) . ' Empty it before posting.');
            }
            if (($warning = batches_capacity_warning($vessel, $qty)) !== null) {
                $warnings[] = $warning;
            }
            $lot = insert_lot($pdo, '', (int) $output['item_id'], (int) $run['premises_id'], null, null, null, null, 'released', $juiceCost, 'press_run', $id, $userId, (string) $run['run_on']);
            if ($output['brix'] !== null) {
                set_lot_attribute($pdo, (int) $lot['id'], 'brix', (float) $output['brix'], null, '°Bx', 'press_run', $userId);
            }
            if ($varieties !== []) {
                set_lot_attribute($pdo, (int) $lot['id'], 'variety', null, implode('/', array_keys($varieties)), null, 'press_run', $userId);
            }
            set_lot_attribute($pdo, (int) $lot['id'], 'press_run', null, (string) $run['number'], null, 'press_run', $userId);
            insert_inventory_transaction($pdo, $group, 'production_output', (int) $output['item_id'], (int) $lot['id'], (int) $vessel['location_id'], (int) $run['premises_id'],
                $qty, $juiceCost, 'press_run', $id, null, 'produced', 'press_run', $id, 'press_run:' . $id . ':' . (++$line), $at, $userId);
            insert_vessel_occupancy($pdo, (int) $vessel['id'], 'lot', (int) $lot['id'], $qty, $at);
            set_vessel_status($pdo, (int) $vessel['id'], 'in_use');
        } else {
            $lot = insert_lot($pdo, '', (int) $output['item_id'], (int) $run['premises_id'], null, null, null, null, 'released', 0.0, 'press_run', $id, $userId, (string) $run['run_on']);
            insert_inventory_transaction($pdo, $group, 'production_output', (int) $output['item_id'], (int) $lot['id'], (int) $output['location_id'], (int) $run['premises_id'],
                $qty, 0.0, 'press_run', $id, null, 'none', 'press_run', $id, 'press_run:' . $id . ':' . (++$line), $at, $userId);
            $pomaceKg += $qty;
        }
        $setLot->execute(['lot' => $lot['id'], 'id' => $output['id']]);
        $lots[] = ['kind' => $output['kind'], 'lot_id' => (int) $lot['id'], 'lot_number' => $lot['lot_number'], 'qty_base' => round($qty, 3)];
    }
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.press_runs SET fruit_kg_total = :fruit, juice_l_total = :juice, pomace_kg_total = :pomace, yield_l_per_kg = :yield,
               status = 'posted', posted_by = :by, posted_at = now()
        WHERE id = :id AND status = 'draft'
        RETURNING fruit_kg_total, juice_l_total, pomace_kg_total, yield_l_per_kg
    SQL);
    $statement->execute(['fruit' => round($fruitKg, 3), 'juice' => round($juiceL, 3), 'pomace' => round($pomaceKg, 3), 'yield' => $fruitKg > 0 ? round($juiceL / $fruitKg, 6) : null, 'by' => $userId, 'id' => $id]);
    return ['run' => $run, 'totals' => $statement->fetch(), 'lots' => $lots, 'capacity_warnings' => $warnings, 'ledger_group_id' => $group];
}

/** Everything the press-run form selects from. */
function press_run_form_catalogs(PDO $pdo): array
{
    $vesselCatalog = batches_vessel_catalog($pdo);
    return [
        'fruitLots' => find_fruit_lot_options($pdo), 'outputItems' => press_run_output_items($pdo), 'vesselCatalog' => $vesselCatalog,
        'vessels' => batches_vessel_options($vesselCatalog), 'locations' => batches_location_options($pdo), 'pressVessels' => press_vessel_options($pdo),
        'premises' => premises_options($pdo),
    ];
}

/** Stored lines as form rows (display units), keyed n1, n2, ... */
function press_run_form_rows(array $inputs, array $outputs): array
{
    $in = [];
    foreach (array_values($inputs) as $i => $input) {
        $in['n' . ($i + 1)] = ['lot_id' => (int) $input['lot_id'], 'qty_lb' => round((float) to_display($input['qty_kg'], 'kg', 'fruit'), 3)];
    }
    $out = [];
    foreach (array_values($outputs) as $i => $output) {
        $out['n' . ($i + 1)] = ['kind' => $output['kind'], 'item_id' => (int) $output['item_id'], 'qty' => round((float) to_display($output['qty_base'], $output['base_unit_code']), 3),
            'brix' => $output['brix'] !== null ? (float) $output['brix'] : '', 'vessel_id' => $output['vessel_id'], 'location_id' => $output['location_id']];
    }
    return [$in, $out];
}
