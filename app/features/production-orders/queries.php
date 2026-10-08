<?php
declare(strict_types=1);

require_once __DIR__ . '/../reservations/queries.php';

const PRODUCTION_ORDER_STATUSES = ['planned' => 'Planned', 'released' => 'Released', 'in_progress' => 'In progress', 'complete' => 'Complete', 'closed' => 'Closed', 'cancelled' => 'Cancelled'];
const PRODUCTION_ORDER_DEFAULT_STATUSES = ['planned', 'released', 'in_progress'];
const PRODUCTION_ORDER_ACTIVE_STATUSES = ['planned', 'released', 'in_progress'];
const PRODUCTION_ORDER_STATUS_COLORS = ['planned' => 'dark', 'released' => 'info', 'in_progress' => 'info', 'complete' => 'success', 'closed' => 'secondary', 'cancelled' => 'danger'];
const PRODUCTION_ORDER_SORTS = ['number' => 'po.number', 'planned_pitch_on' => 'po.planned_pitch_on', 'planned_package_on' => 'po.planned_package_on', 'status' => 'po.status', 'product_name' => 'p.name'];

function production_order_status_color(string $status): string
{
    return PRODUCTION_ORDER_STATUS_COLORS[$status] ?? 'secondary';
}

function production_order_status_dot(string $status): string
{
    return status_dot(production_order_status_color($status));
}

function production_order_status_badge(string $status, ?string $id = null): string
{
    return badge(humanize($status), production_order_status_color($status), $id);
}

/**
 * Per item: released-lot qty_available, open soft allocations of OTHER production orders, and the net available.
 * inventory_balances is never touched; allocations are subtracted here.
 */
function production_item_availability(PDO $pdo, array $itemIds, int $excludeOrderId): array
{
    $out = [];
    $released = $pdo->prepare("SELECT COALESCE(sum(qty_available), 0) FROM app.v_lot_balances WHERE item_id = :i AND quality_status = 'released'");
    $others = $pdo->prepare('SELECT COALESCE(sum(qty_base), 0) FROM app.allocations WHERE item_id = :i AND released_at IS NULL AND production_order_id <> :o');
    foreach (array_unique(array_map('intval', $itemIds)) as $itemId) {
        $released->execute(['i' => $itemId]);
        $others->execute(['i' => $itemId, 'o' => $excludeOrderId]);
        $r = (float) $released->fetchColumn();
        $a = (float) $others->fetchColumn();
        $out[$itemId] = ['released_base' => $r, 'allocated_other_base' => $a, 'available_base' => $r - $a];
    }
    return $out;
}

/** $statusFilter: list of statuses; empty means all. */
function find_production_orders(PDO $pdo, string $search = '', array $statusFilter = [], string $sort = '-planned_pitch_on', int $page = 1): array
{
    [$where, $params] = production_orders_filter($search, $statusFilter);
    $from = ' FROM app.production_orders po JOIN app.products p ON p.id = po.product_id JOIN app.recipe_versions rv ON rv.id = po.recipe_version_id';
    return paged_query(
        $pdo,
        'SELECT po.id, po.number, po.status, po.planned_volume_l, po.planned_pitch_on, po.planned_package_on, p.name AS product_name, rv.version_no' . $from . $where
            . ' ORDER BY ' . order_by($sort, PRODUCTION_ORDER_SORTS, '-planned_pitch_on') . ', po.id DESC',
        'SELECT count(*)' . $from . $where,
        $params,
        $page
    );
}

function count_production_orders(PDO $pdo, string $search = '', array $statusFilter = []): int
{
    [$where, $params] = production_orders_filter($search, $statusFilter);
    $statement = $pdo->prepare('SELECT count(*) FROM app.production_orders po JOIN app.products p ON p.id = po.product_id' . $where);
    $statement->execute($params);
    return (int) $statement->fetchColumn();
}

function production_orders_filter(string $search, array $statusFilter): array
{
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(po.number ILIKE :s OR p.name ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    if ($statusFilter !== []) {
        $marks = [];
        foreach (array_values($statusFilter) as $i => $status) {
            $marks[] = ':st' . $i;
            $params['st' . $i] = $status;
        }
        $where[] = 'po.status IN (' . implode(', ', $marks) . ')';
    }
    return [$where === [] ? '' : ' WHERE ' . implode(' AND ', $where), $params];
}

function find_production_order(PDO $pdo, int $id, bool $lock = false): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT po.*, p.name AS product_name, rv.version_no, rv.status AS recipe_status, pr.name AS premises_name,
               uc.display_name AS created_by_name, ur.display_name AS released_by_name, ux.display_name AS closed_by_name
        FROM app.production_orders po
        JOIN app.products p ON p.id = po.product_id
        JOIN app.recipe_versions rv ON rv.id = po.recipe_version_id
        JOIN app.premises pr ON pr.id = po.premises_id
        LEFT JOIN app.users uc ON uc.id = po.created_by
        LEFT JOIN app.users ur ON ur.id = po.released_by
        LEFT JOIN app.users ux ON ux.id = po.closed_by
        WHERE po.id = :id
    SQL . ($lock ? ' FOR UPDATE OF po' : ''));
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/**
 * One row per recipe line. required = qty_per_batch_base, else qty_per_l * planned volume.
 * available = released lots (v_lot_balances) minus open allocations of other orders, on order = v_item_stock.qty_on_order.
 */
function find_material_check(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT rl.id, rl.seq, rl.item_id, rl.purpose, i.code AS item_code, i.name AS item_name, i.base_unit_code, i.item_class,
               COALESCE(rl.qty_per_batch_base, rl.qty_per_l * po.planned_volume_l) AS required_base,
               COALESCE(s.qty_on_order, 0) AS on_order_base
        FROM app.production_orders po
        JOIN app.recipe_lines rl ON rl.recipe_version_id = po.recipe_version_id
        JOIN app.items i ON i.id = rl.item_id
        LEFT JOIN app.v_item_stock s ON s.item_id = rl.item_id
        WHERE po.id = :id
        ORDER BY rl.seq
    SQL);
    $statement->execute(['id' => $id]);
    $rows = $statement->fetchAll();
    $availability = production_item_availability($pdo, array_column($rows, 'item_id'), $id);
    foreach ($rows as &$row) {
        $row['released_base'] = $availability[(int) $row['item_id']]['released_base'];
        $row['allocated_other_base'] = $availability[(int) $row['item_id']]['allocated_other_base'];
        $row['available_base'] = max(0.0, $availability[(int) $row['item_id']]['available_base']);
        $row['shortfall_base'] = max(0.0, (float) $row['required_base'] - (float) $row['available_base']);
    }
    unset($row);
    return production_pick_plan($pdo, $id, $rows);
}

/**
 * Where to pick each material line from, first in first out: released stock on the order's
 * premises (app.v_fifo_stock), oldest lot first, then by area and rack number, taken until the
 * line's required quantity is covered. Recipe lines that share an item draw down the same lots
 * in recipe order. Adds to each row: picks (list of [lot_id, lot_number, where, qty_base,
 * fifo_rank]) and pick_short_base (required quantity no released stock covers).
 */
function production_pick_plan(PDO $pdo, int $orderId, array $rows): array
{
    if ($rows === []) {
        return $rows;
    }
    $itemIds = array_values(array_unique(array_map('intval', array_column($rows, 'item_id'))));
    $in = implode(', ', array_map(static fn($i) => ':i' . $i, array_keys($itemIds)));
    $statement = $pdo->prepare(<<<SQL
        SELECT fs.item_id, fs.lot_id, fs.lot_number, fs.location_name, fs.area_name, fs.rack_number, fs.qty_available, fs.fifo_rank
        FROM app.v_fifo_stock fs
        WHERE fs.premises_id = (SELECT premises_id FROM app.production_orders WHERE id = :o)
          AND fs.fifo_rank IS NOT NULL AND fs.qty_available > 0 AND fs.item_id IN ({$in})
        ORDER BY fs.item_id, fs.fifo_rank, fs.area_name, fs.rack_sort NULLS FIRST
    SQL);
    $statement->bindValue('o', $orderId, PDO::PARAM_INT);
    foreach ($itemIds as $i => $itemId) {
        $statement->bindValue('i' . $i, $itemId, PDO::PARAM_INT);
    }
    $statement->execute();
    $stock = [];
    foreach ($statement->fetchAll() as $s) {
        $s['left'] = (float) $s['qty_available'];
        $s['where'] = $s['rack_number'] !== null ? $s['area_name'] . ' · Rack ' . $s['rack_number'] : $s['location_name'];
        $stock[(int) $s['item_id']][] = $s;
    }
    foreach ($rows as &$row) {
        $need = (float) $row['required_base'];
        $row['picks'] = [];
        foreach ($stock[(int) $row['item_id']] ?? [] as $k => $s) {
            if ($need <= 0.0) {
                break;
            }
            if ($s['left'] <= 0.0) {
                continue;
            }
            $take = min($need, $s['left']);
            $stock[(int) $row['item_id']][$k]['left'] -= $take;
            $need -= $take;
            $row['picks'][] = ['lot_id' => (int) $s['lot_id'], 'lot_number' => $s['lot_number'], 'where' => $s['where'], 'qty_base' => $take, 'fifo_rank' => (int) $s['fifo_rank']];
        }
        $row['pick_short_base'] = max(0.0, $need);
    }
    unset($row);
    return $rows;
}

function find_allocations(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT a.*, i.code AS item_code, i.name AS item_name, i.base_unit_code, l.lot_number
        FROM app.allocations a JOIN app.items i ON i.id = a.item_id LEFT JOIN app.lots l ON l.id = a.lot_id
        WHERE a.production_order_id = :id ORDER BY a.id
    SQL);
    $statement->execute(['id' => $id]);
    return $statement->fetchAll();
}

/** id => label for the recipe select: versions of the product with status active or draft. */
function find_recipe_options(PDO $pdo, int $productId): array
{
    $statement = $pdo->prepare("SELECT id, version_no, status, target_batch_volume_l FROM app.recipe_versions WHERE product_id = :p AND status IN ('active', 'draft') ORDER BY (status = 'active') DESC, version_no DESC");
    $statement->execute(['p' => $productId]);
    return $statement->fetchAll();
}

/** id => name of active cider products. */
function production_product_options(PDO $pdo): array
{
    return $pdo->query("SELECT id, name FROM app.products WHERE status = 'active' AND beverage_type = 'cider' ORDER BY name")->fetchAll(PDO::FETCH_KEY_PAIR);
}

function production_default_premises_id(array $premises): ?int
{
    return count($premises) === 1 ? (int) array_key_first($premises) : null;
}

function insert_production_order(PDO $pdo, int $premisesId, int $productId, int $recipeVersionId, float $plannedVolumeL, ?string $plannedPitchOn, ?string $plannedPackageOn, ?string $notes, int $createdBy): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.production_orders (number, premises_id, product_id, recipe_version_id, planned_volume_l, planned_pitch_on, planned_package_on, status, notes, created_by)
        VALUES (app.next_number('production_order'), :premises, :product, :recipe, :volume, :pitch, :package, 'planned', :notes, :by)
        RETURNING id, number, premises_id, product_id, recipe_version_id, planned_volume_l, planned_pitch_on, planned_package_on, status, notes
    SQL);
    $statement->execute(['premises' => $premisesId, 'product' => $productId, 'recipe' => $recipeVersionId, 'volume' => round($plannedVolumeL, 3), 'pitch' => $plannedPitchOn, 'package' => $plannedPackageOn, 'notes' => $notes, 'by' => $createdBy]);
    return $statement->fetch();
}

function update_production_order(PDO $pdo, int $id, int $productId, int $recipeVersionId, float $plannedVolumeL, ?string $plannedPitchOn, ?string $plannedPackageOn, ?string $notes, ?int $premisesId = null): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.production_orders
        SET product_id = :product, recipe_version_id = :recipe, planned_volume_l = :volume, planned_pitch_on = :pitch, planned_package_on = :package, notes = :notes,
            premises_id = COALESCE(:premises, premises_id)
        WHERE id = :id AND status = 'planned'
        RETURNING id, number, premises_id, product_id, recipe_version_id, planned_volume_l, planned_pitch_on, planned_package_on, status, notes
    SQL);
    $statement->bindValue('premises', $premisesId, $premisesId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    foreach (['id' => $id, 'product' => $productId, 'recipe' => $recipeVersionId, 'volume' => round($plannedVolumeL, 3), 'pitch' => $plannedPitchOn, 'package' => $plannedPackageOn, 'notes' => $notes] as $key => $value) {
        $statement->bindValue($key, $value, $value === null ? PDO::PARAM_NULL : (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR));
    }
    $statement->execute();
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Only planned production orders can be edited.');
    }
    return $row;
}

/** planned -> released; one soft allocation (lot_id NULL) per recipe line. Returns the order row plus allocation ids. */
function release_production_order(PDO $pdo, int $id, int $userId): array
{
    $statement = $pdo->prepare("UPDATE app.production_orders SET status = 'released', released_by = :by, released_at = now() WHERE id = :id AND status = 'planned' RETURNING id, number, status, released_at");
    $statement->execute(['id' => $id, 'by' => $userId]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Only planned production orders can be released.');
    }
    $insert = $pdo->prepare('INSERT INTO app.allocations (production_order_id, item_id, lot_id, qty_base) VALUES (:id, :item, NULL, :qty) RETURNING id');
    $ids = [];
    foreach (find_material_check($pdo, $id) as $line) {
        if ((float) $line['required_base'] > 0) {
            $insert->execute(['id' => $id, 'item' => $line['item_id'], 'qty' => round((float) $line['required_base'], 4)]);
            $ids[] = (int) $insert->fetchColumn();
        }
    }
    $row['allocation_ids'] = $ids;
    return $row;
}

function release_production_order_allocations(PDO $pdo, int $id): int
{
    $statement = $pdo->prepare('UPDATE app.allocations SET released_at = now() WHERE production_order_id = :id AND released_at IS NULL');
    $statement->execute(['id' => $id]);
    return $statement->rowCount();
}

function close_production_order(PDO $pdo, int $id, int $userId): array
{
    $statement = $pdo->prepare("UPDATE app.production_orders SET status = 'closed', closed_by = :by, closed_at = now() WHERE id = :id AND status IN ('complete', 'in_progress') RETURNING id, number, status, closed_at");
    $statement->execute(['id' => $id, 'by' => $userId]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Only in-progress or complete production orders can be closed.');
    }
    $row['allocations_released'] = release_production_order_allocations($pdo, $id);
    return $row;
}

function cancel_production_order(PDO $pdo, int $id, int $userId): array
{
    $statement = $pdo->prepare("UPDATE app.production_orders SET status = 'cancelled' WHERE id = :id AND status IN ('planned', 'released') RETURNING id, number, status");
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Only planned or released production orders can be cancelled.');
    }
    $row['allocations_released'] = release_production_order_allocations($pdo, $id);
    return $row;
}

// The equipment plan: the order's bookings in app.equipment_reservations (db/023) -----------------------------------

/** Which recipe stage a booking's role serves, for the Processing time card. */
const PRODUCTION_ROLE_STAGES = ['primary' => 'primary', 'maturation' => 'maturation', 'brite' => 'carbonate', 'blend' => 'blend', 'press' => 'press',
    'mill' => 'press', 'transfer' => 'rack', 'filter' => 'maturation', 'carbonate' => 'carbonate', 'package' => 'package'];

/** The order's booked reservations, each with its clashes and the vessel's foreign occupant (null when it holds this order's batch). */
function find_order_plan(PDO $pdo, int $orderId): array
{
    $rows = find_subject_reservations($pdo, 'production_order', $orderId);
    foreach ($rows as &$row) {
        $row['clashes'] = reservation_clashes($pdo, $row['resource_kind'], (int) $row['resource_id'], $row['starts_at'], $row['ends_at'], (int) $row['id']);
        $row['occupant'] = find_resource_occupant($pdo, $row['resource_kind'], (int) $row['resource_id'], 'production_order', $orderId);
    }
    unset($row);
    return $rows;
}

/** A plan row as the form shows it, from a schedule row. */
function production_plan_row_from_reservation(array $r): array
{
    return ['id' => (int) $r['id'], 'resource' => $r['resource_kind'] . ':' . (int) $r['resource_id'], 'role' => $r['role'],
        'planned_from' => $r['local_from'], 'planned_to' => $r['local_to'], 'all_day' => (bool) $r['all_day'],
        'start_time' => $r['all_day'] ? '' : (new DateTimeImmutable($r['starts_at']))->setTimezone(new DateTimeZone((string) config('app.timezone')))->format('H:i'),
        'end_time' => $r['all_day'] ? '' : (new DateTimeImmutable($r['ends_at']))->setTimezone(new DateTimeZone((string) config('app.timezone')))->format('H:i'),
        'share' => (bool) $r['shared']];
}

/**
 * Validate the repeating plan rows (plan[n][resource|role|planned_from|planned_to|all_day|start_time|end_time|share|id];
 * the older vessels[n][vessel_id] shape is read too). Pure apart from the window maths. Returns [cleanRows, errorsByRow];
 * blank rows are skipped. Each clean row carries starts_at and ends_at when its window is valid.
 */
function production_validate_plan_rows(array $raw, array $catalog): array
{
    $rows = [];
    $errors = [];
    foreach ($raw as $n => $r) {
        if (!is_array($r)) {
            continue;
        }
        $resource = trim((string) ($r['resource'] ?? ''));
        if ($resource === '' && (int) ($r['vessel_id'] ?? 0) > 0) {
            $resource = 'vessel:' . (int) $r['vessel_id'];
        }
        $from = trim((string) ($r['planned_from'] ?? ''));
        $to = trim((string) ($r['planned_to'] ?? ''));
        if ($resource === '' && $from === '' && $to === '') {
            continue;
        }
        $rowErrors = [];
        if (!isset($catalog[$resource])) {
            $rowErrors['resource'] = 'Choose a vessel or a piece of equipment.';
        }
        $role = trim((string) ($r['role'] ?? ''));
        if ($role === '') {
            $role = ($catalog[$resource]['resource_kind'] ?? 'vessel') === 'vessel' ? 'primary' : 'other';
        }
        if (!isset(RESERVATION_ROLES[$role])) {
            $rowErrors['role'] = 'Choose a role.';
        }
        $allDay = !array_key_exists('all_day', $r) || in_array((string) (is_array($r['all_day']) ? end($r['all_day']) : $r['all_day']), ['1', 'on', 'true', 'yes'], true);
        $startTime = trim((string) ($r['start_time'] ?? ''));
        $endTime = trim((string) ($r['end_time'] ?? ''));
        $window = reservation_window($from, $to, $allDay, $startTime, $endTime);
        if (is_string($window)) {
            $rowErrors[$from === '' ? 'planned_from' : 'planned_to'] = $window;
        }
        if ($rowErrors !== []) {
            $errors[$n] = $rowErrors;
        }
        $key = reservation_resource_key($resource);
        $rows[$n] = ['id' => (int) ($r['id'] ?? 0) ?: null, 'resource' => $resource, 'resource_kind' => $key[0] ?? null, 'resource_id' => $key[1] ?? null,
            'role' => $role, 'planned_from' => $from, 'planned_to' => $to, 'all_day' => $allDay, 'start_time' => $startTime, 'end_time' => $endTime,
            'share' => in_array((string) (is_array($r['share'] ?? null) ? end($r['share']) : ($r['share'] ?? '0')), ['1', 'on', 'true', 'yes'], true),
            'starts_at' => is_array($window) ? $window[0] : null, 'ends_at' => is_array($window) ? $window[1] : null];
    }
    return [$rows, $errors];
}

/**
 * The clash rule for every valid row: a row whose window overlaps a booking on its resource is refused, unless the
 * organization allows double booking and the row's "Book anyway" is ticked — then it saves shared. Adds clash_rows and
 * shared to each row; returns the errors by row.
 */
function production_plan_clashes(PDO $pdo, array &$rows, array $catalog, bool $allowShare): array
{
    $errors = [];
    foreach ($rows as $n => &$row) {
        $row['clash_rows'] = [];
        $row['shared'] = false;
        if ($row['starts_at'] === null || !isset($catalog[$row['resource']])) {
            continue;
        }
        $clashes = reservation_clashes($pdo, $row['resource_kind'], (int) $row['resource_id'], $row['starts_at'], $row['ends_at'], $row['id']);
        $row['clash_rows'] = $clashes;
        if ($clashes === []) {
            continue;
        }
        if ($allowShare && $row['share']) {
            $row['shared'] = true;
            continue;
        }
        $errors[$n]['clashes'] = $allowShare ? 'Already booked — tick "Book anyway" to share it, or change the window.' : 'Already booked; the organization does not allow double booking.';
    }
    unset($row);
    return $errors;
}

/** Ids of the order's booked reservations. */
function find_order_plan_ids(PDO $pdo, int $orderId): array
{
    $statement = $pdo->prepare("SELECT id FROM app.equipment_reservations WHERE subject_kind = 'production_order' AND subject_id = :id AND status = 'booked'");
    $statement->execute(['id' => $orderId]);
    return array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Write the plan inside the caller's transaction: rows that name one of the order's bookings are updated in place (they
 * keep their id and shared mark), new rows are inserted, the order's bookings the form no longer lists are deleted.
 * Returns the saved rows.
 */
function save_production_order_plan(PDO $pdo, int $orderId, array $rows, int $userId): array
{
    $existing = find_order_plan_ids($pdo, $orderId);
    $kept = [];
    $saved = [];
    foreach (array_values($rows) as $row) {
        $data = ['resource_kind' => $row['resource_kind'], 'resource_id' => $row['resource_id'], 'kind' => 'run', 'subject_kind' => 'production_order', 'subject_id' => $orderId,
            'role' => $row['role'], 'starts_at' => $row['starts_at'], 'ends_at' => $row['ends_at'], 'all_day' => $row['all_day'], 'shared' => $row['shared'] ?? false, 'notes' => null];
        if ($row['id'] !== null && in_array($row['id'], $existing, true)) {
            $out = update_reservation($pdo, $row['id'], $data);
        } else {
            $out = insert_reservation($pdo, $data, $userId);
        }
        $kept[] = (int) $out['id'];
        $saved[] = $out;
    }
    foreach (array_diff($existing, $kept) as $gone) {
        delete_reservation($pdo, $gone);
    }
    return $saved;
}

// Processing time and outputs (the production order as a run) ------------------------------------------------------

/**
 * The recipe's stages in order with expected days, a planned start and end rolled forward from the planned pitch date,
 * the actual entry and exit of the order's batches, the variance in days, and the plan rows whose role serves the
 * stage. Returns ['stages' => rows, 'planned_days' => int, 'planned_end' => date|null, 'elapsed_days' => int|null].
 */
function find_order_processing_time(PDO $pdo, array $order, array $plan): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT rs.seq, rs.stage_code, st.name AS stage_name, rs.expected_duration_days, rs.expected_loss_pct, rs.instructions
          FROM app.recipe_stages rs JOIN app.stages st ON st.code = rs.stage_code
         WHERE rs.recipe_version_id = :rv ORDER BY rs.seq
    SQL);
    $statement->execute(['rv' => (int) $order['recipe_version_id']]);
    $stages = $statement->fetchAll();
    $actual = $pdo->prepare(<<<'SQL'
        SELECT se.stage_code, min(se.entered_at) AS entered_at,
               CASE WHEN bool_or(se.left_at IS NULL) THEN NULL ELSE max(se.left_at) END AS left_at,
               string_agg(DISTINCT b.number, ', ' ORDER BY b.number) AS batches
          FROM app.stage_events se JOIN app.batches b ON b.id = se.batch_id
         WHERE b.production_order_id = :id GROUP BY se.stage_code
    SQL);
    $actual->execute(['id' => (int) $order['id']]);
    $byStage = [];
    foreach ($actual->fetchAll() as $a) {
        $byStage[$a['stage_code']] = $a;
    }
    $tz = new DateTimeZone((string) config('app.timezone'));
    $cursor = $order['planned_pitch_on'] ? new DateTimeImmutable($order['planned_pitch_on'], $tz) : null;
    $plannedDays = 0;
    $now = new DateTimeImmutable('now', $tz);
    foreach ($stages as &$stage) {
        $days = $stage['expected_duration_days'] === null ? null : (int) $stage['expected_duration_days'];
        $stage['planned_start'] = $cursor?->format('Y-m-d');
        $stage['planned_end'] = $cursor === null ? null : $cursor->modify('+' . max(0, ($days ?? 1) - 1) . ' days')->format('Y-m-d');
        if ($cursor !== null) {
            $cursor = $cursor->modify('+' . max(1, $days ?? 1) . ' days');
        }
        $plannedDays += $days ?? 0;
        $a = $byStage[$stage['stage_code']] ?? null;
        $stage['entered_at'] = $a['entered_at'] ?? null;
        $stage['left_at'] = $a['left_at'] ?? null;
        $stage['batches'] = $a['batches'] ?? null;
        $stage['actual_days'] = null;
        $stage['variance_days'] = null;
        if ($a !== null) {
            $entered = new DateTimeImmutable($a['entered_at']);
            $until = $a['left_at'] !== null ? new DateTimeImmutable($a['left_at']) : $now;
            $stage['actual_days'] = max(0.0, round(($until->getTimestamp() - $entered->getTimestamp()) / 86400, 1));
            $stage['in_progress'] = $a['left_at'] === null;
            if ($days !== null && $a['left_at'] !== null) {
                $stage['variance_days'] = round($stage['actual_days'] - $days, 1);
            }
        }
        $stage['bookings'] = array_values(array_filter($plan, static fn($b) => (PRODUCTION_ROLE_STAGES[$b['role']] ?? null) === $stage['stage_code']));
    }
    unset($stage);
    $started = null;
    foreach ($byStage as $a) {
        if ($started === null || $a['entered_at'] < $started) {
            $started = $a['entered_at'];
        }
    }
    return [
        'stages' => $stages,
        'planned_days' => $plannedDays,
        'planned_end' => $cursor === null ? null : $cursor->modify('-1 day')->format('Y-m-d'),
        'elapsed_days' => $started === null ? null : (int) floor(($now->getTimestamp() - (new DateTimeImmutable($started))->getTimestamp()) / 86400),
    ];
}

/** Planned and actual outputs: planned packages, the batches, their packaging runs and the finished lots with units on hand. */
function find_order_outputs(PDO $pdo, array $order): array
{
    $id = (int) $order['id'];
    $packages = $pdo->prepare(<<<'SQL'
        SELECT pop.id, pop.planned_units, pop.share_pct, pc.id AS configuration_id, pc.name AS package_name, pc.package_kind, pc.fill_volume_l, pc.units_per_case,
               sol.id AS line_id, so.id AS sales_order_id, so.number AS sales_order_number
          FROM app.production_order_packages pop
          JOIN app.packaging_configurations pc ON pc.id = pop.packaging_configuration_id
          LEFT JOIN app.sales_order_lines sol ON sol.id = pop.sales_order_line_id
          LEFT JOIN app.sales_orders so ON so.id = sol.sales_order_id
         WHERE pop.production_order_id = :id ORDER BY pop.id
    SQL);
    $packages->execute(['id' => $id]);
    $batches = $pdo->prepare('SELECT b.id, b.number, b.status, b.current_stage_code, st.name AS stage_name, b.current_volume_l, b.started_at FROM app.batches b JOIN app.stages st ON st.code = b.current_stage_code WHERE b.production_order_id = :id ORDER BY b.number');
    $batches->execute(['id' => $id]);
    $runs = $pdo->prepare(<<<'SQL'
        SELECT k.id, k.number, k.status, k.run_on, k.units_out, k.volume_in_l, k.volume_out_l, k.loss_l, pc.name AS package_name, b.number AS batch_number
          FROM app.packaging_runs k JOIN app.batches b ON b.id = k.batch_id JOIN app.packaging_configurations pc ON pc.id = k.packaging_configuration_id
         WHERE b.production_order_id = :id AND k.status <> 'cancelled' ORDER BY k.run_on, k.number
    SQL);
    $runs->execute(['id' => $id]);
    $lots = $pdo->prepare(<<<'SQL'
        SELECT fl.lot_id, l.lot_number, fl.units_packaged, fl.packaged_on, fl.tax_class, pc.name AS package_name, b.number AS batch_number,
               COALESCE((SELECT sum(vb.qty_on_hand) FROM app.v_lot_balances vb WHERE vb.lot_id = fl.lot_id), 0) AS units_on_hand
          FROM app.finished_lots fl JOIN app.lots l ON l.id = fl.lot_id JOIN app.batches b ON b.id = fl.batch_id
          JOIN app.packaging_configurations pc ON pc.id = fl.packaging_configuration_id
         WHERE b.production_order_id = :id ORDER BY fl.packaged_on, l.lot_number
    SQL);
    $lots->execute(['id' => $id]);
    $loss = $pdo->prepare('SELECT COALESCE(rv.expected_total_loss_pct, (SELECT sum(rs.expected_loss_pct) FROM app.recipe_stages rs WHERE rs.recipe_version_id = rv.id), 0) FROM app.recipe_versions rv WHERE rv.id = :rv');
    $loss->execute(['rv' => (int) $order['recipe_version_id']]);
    $lossPct = (float) $loss->fetchColumn();
    return [
        'planned_volume_l' => (float) $order['planned_volume_l'],
        'expected_loss_pct' => $lossPct,
        'expected_packaged_l' => (float) $order['planned_volume_l'] * (1 - $lossPct / 100),
        'packages' => $packages->fetchAll(),
        'batches' => $batches->fetchAll(),
        'runs' => $runs->fetchAll(),
        'lots' => $lots->fetchAll(),
    ];
}

/** What the order's batches consumed so far, by item: item_id => qty_base. */
function find_order_consumed(PDO $pdo, int $orderId): array
{
    $statement = $pdo->prepare('SELECT c.item_id, sum(c.qty_base) AS qty FROM app.consumptions c JOIN app.batches b ON b.id = c.batch_id WHERE b.production_order_id = :id GROUP BY c.item_id');
    $statement->execute(['id' => $orderId]);
    return array_map('floatval', $statement->fetchAll(PDO::FETCH_KEY_PAIR));
}
