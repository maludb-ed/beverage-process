<?php
declare(strict_types=1);

const PRODUCTION_ORDER_STATUSES = ['planned' => 'Planned', 'released' => 'Released', 'in_progress' => 'In progress', 'complete' => 'Complete', 'closed' => 'Closed', 'cancelled' => 'Cancelled'];
const PRODUCTION_ORDER_DEFAULT_STATUSES = ['planned', 'released', 'in_progress'];
const PRODUCTION_ORDER_ACTIVE_STATUSES = ['planned', 'released', 'in_progress'];
const PRODUCTION_ORDER_VESSEL_ROLES = ['primary' => 'Primary', 'maturation' => 'Maturation', 'brite' => 'Brite', 'blend' => 'Blend'];
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

function find_production_order_vessels(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT pv.*, v.name AS vessel_name, v.capacity_l
        FROM app.production_order_vessels pv JOIN app.vessels v ON v.id = pv.vessel_id
        WHERE pv.production_order_id = :id ORDER BY pv.planned_from, pv.id
    SQL);
    $statement->execute(['id' => $id]);
    return $statement->fetchAll();
}

/** Keyed by production_order_vessels.id: list of ['kind' => 'plan'|'occupancy', 'label' => string]. */
function find_vessel_conflicts(PDO $pdo, int $id): array
{
    $conflicts = [];
    $plans = $pdo->prepare(<<<'SQL'
        SELECT pv.id AS row_id, o.number, o.status
        FROM app.production_order_vessels pv
        JOIN app.production_order_vessels other ON other.vessel_id = pv.vessel_id AND other.production_order_id <> pv.production_order_id
        JOIN app.production_orders o ON o.id = other.production_order_id AND o.status IN ('planned', 'released', 'in_progress')
        WHERE pv.production_order_id = :id
          AND daterange(pv.planned_from, pv.planned_to, '[]') && daterange(other.planned_from, other.planned_to, '[]')
        ORDER BY pv.id, o.number
    SQL);
    $plans->execute(['id' => $id]);
    foreach ($plans->fetchAll() as $row) {
        $conflicts[(int) $row['row_id']][] = ['kind' => 'plan', 'label' => 'Overlaps ' . $row['number']];
    }
    $occupied = $pdo->prepare(<<<'SQL'
        SELECT pv.id AS row_id, b.number AS batch_number, l.lot_number
        FROM app.production_order_vessels pv
        JOIN app.vessel_occupancies vo ON vo.vessel_id = pv.vessel_id AND vo.to_at IS NULL
        LEFT JOIN app.batches b ON vo.occupant_kind = 'batch' AND b.id = vo.occupant_id
        LEFT JOIN app.lots l ON vo.occupant_kind = 'lot' AND l.id = vo.occupant_id
        WHERE pv.production_order_id = :id
          AND NOT (vo.occupant_kind = 'batch' AND COALESCE(b.production_order_id, 0) = pv.production_order_id)
        ORDER BY pv.id
    SQL);
    $occupied->execute(['id' => $id]);
    foreach ($occupied->fetchAll() as $row) {
        $conflicts[(int) $row['row_id']][] = ['kind' => 'occupancy', 'label' => 'Occupied now by ' . ($row['batch_number'] ?? $row['lot_number'] ?? 'another occupant')];
    }
    return $conflicts;
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

/** id => ['label' => 'Name (500.0 gal)', 'capacity_l' => float] for active vessels. */
function production_vessel_catalog(PDO $pdo): array
{
    $catalog = [];
    foreach ($pdo->query('SELECT id, name, capacity_l FROM app.vessels WHERE active ORDER BY name') as $row) {
        $catalog[(int) $row['id']] = ['label' => $row['name'] . ' (' . fmt_qty($row['capacity_l'], 'L') . ')', 'capacity_l' => (float) $row['capacity_l']];
    }
    return $catalog;
}

function production_default_premises_id(array $premises): ?int
{
    return count($premises) === 1 ? (int) array_key_first($premises) : null;
}

/**
 * Validate the repeating vessel rows. Pure: returns [cleanRows, errorsByRow]. Blank rows are skipped.
 * Each clean row: vessel_id, role, planned_from, planned_to.
 */
function production_validate_vessel_rows(array $raw, array $catalog): array
{
    $rows = [];
    $errors = [];
    foreach ($raw as $n => $r) {
        if (!is_array($r)) {
            continue;
        }
        $vesselId = (int) ($r['vessel_id'] ?? 0);
        $from = trim((string) ($r['planned_from'] ?? ''));
        $to = trim((string) ($r['planned_to'] ?? ''));
        if ($vesselId === 0 && $from === '' && $to === '') {
            continue;
        }
        $rowErrors = [];
        if (!isset($catalog[$vesselId])) {
            $rowErrors['vessel_id'] = 'Choose a vessel.';
        }
        $role = (string) ($r['role'] ?? 'primary');
        if (!isset(PRODUCTION_ORDER_VESSEL_ROLES[$role])) {
            $rowErrors['role'] = 'Choose a role.';
        }
        $isDate = static fn(string $d): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && ($dt = DateTimeImmutable::createFromFormat('!Y-m-d', $d)) && $dt->format('Y-m-d') === $d;
        if (!$isDate($from)) {
            $rowErrors['planned_from'] = 'Enter a start date.';
        }
        if (!$isDate($to)) {
            $rowErrors['planned_to'] = 'Enter an end date.';
        } elseif ($isDate($from) && $to < $from) {
            $rowErrors['planned_to'] = 'End date must be on or after the start date.';
        }
        if ($rowErrors !== []) {
            $errors[$n] = $rowErrors;
        }
        $rows[$n] = ['vessel_id' => $vesselId ?: null, 'role' => $role, 'planned_from' => $from, 'planned_to' => $to];
    }
    return [$rows, $errors];
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

/** Delete + insert inside the caller's transaction. Rows: vessel_id, role, planned_from, planned_to. */
function replace_production_order_vessels(PDO $pdo, int $id, array $rows): void
{
    $pdo->prepare('DELETE FROM app.production_order_vessels WHERE production_order_id = :id')->execute(['id' => $id]);
    $insert = $pdo->prepare('INSERT INTO app.production_order_vessels (production_order_id, vessel_id, role, planned_from, planned_to) VALUES (:id, :vessel, :role, :from, :to)');
    foreach (array_values($rows) as $row) {
        $insert->execute(['id' => $id, 'vessel' => $row['vessel_id'], 'role' => $row['role'], 'from' => $row['planned_from'], 'to' => $row['planned_to']]);
    }
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

/**
 * Calendar data: ['plans' => planned vessel rows overlapping [from, to] on planned/released/in_progress orders,
 * 'occupants' => open vessel_occupancies per vessel (from v_vessel_board)].
 */
function find_calendar_rows(PDO $pdo, string $from, string $to): array
{
    $plans = $pdo->prepare(<<<'SQL'
        SELECT pv.id, pv.vessel_id, pv.role, pv.planned_from, pv.planned_to, o.id AS order_id, o.number, o.status
        FROM app.production_order_vessels pv JOIN app.production_orders o ON o.id = pv.production_order_id
        WHERE o.status IN ('planned', 'released', 'in_progress')
          AND daterange(pv.planned_from, pv.planned_to, '[]') && daterange(:from::date, :to::date, '[]')
        ORDER BY pv.planned_from, o.number
    SQL);
    $plans->execute(['from' => $from, 'to' => $to]);
    $occupants = $pdo->query("SELECT vb.vessel_id, vb.occupant_kind, vb.occupant_id, vb.occupant_label, b.production_order_id FROM app.v_vessel_board vb LEFT JOIN app.batches b ON vb.occupant_kind = 'batch' AND b.id = vb.occupant_id WHERE vb.occupant_kind IS NOT NULL")->fetchAll();
    $vessels = $pdo->query('SELECT id, name, capacity_l FROM app.vessels WHERE active ORDER BY name')->fetchAll();
    return ['vessels' => $vessels, 'plans' => $plans->fetchAll(), 'occupants' => $occupants];
}
