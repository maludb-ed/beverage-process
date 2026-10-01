<?php
declare(strict_types=1);

require_once __DIR__ . '/ledger.php';

const INVENTORY_PAGE_SIZE = 50;
const INVENTORY_BALANCE_SORTS = ['item_name' => 'item_name', 'lot_number' => 'lot_number', 'location_name' => 'location_name', 'qty_on_hand' => 'qty_on_hand', 'expires_on' => 'expires_on'];
const INVENTORY_MOVEMENT_SORTS = ['occurred_at' => 't.occurred_at', 'item_name' => 'i.name', 'qty_base' => 't.qty_base'];
const INVENTORY_REORDER_SORTS = ['name' => 'name', 'qty_available' => 'qty_available', 'shortfall' => '(reorder_point_base - qty_available - qty_on_order)'];
const INVENTORY_TXN_TYPES = [
    'receipt' => 'Receipt', 'issue' => 'Issue', 'transfer_out' => 'Transfer out', 'transfer_in' => 'Transfer in', 'adjustment' => 'Adjustment',
    'count_correction' => 'Count correction', 'production_output' => 'Production output', 'packaging_output' => 'Packaging output',
    'removal' => 'Removal', 'return' => 'Return', 'destruction' => 'Destruction', 'reversal' => 'Reversal',
];
const INVENTORY_TXN_COLORS = [
    'receipt' => 'success', 'transfer_in' => 'success', 'production_output' => 'success', 'packaging_output' => 'success', 'return' => 'success',
    'issue' => 'info', 'transfer_out' => 'info', 'removal' => 'info',
    'adjustment' => 'warning', 'count_correction' => 'warning',
    'destruction' => 'danger', 'reversal' => 'danger',
];
const INVENTORY_REFERENCE_URLS = ['goods_receipt' => '/receipts/', 'transfer' => '/transfers/', 'adjustment' => '/adjustments/', 'count' => '/counts/',
    'press_run' => '/press-runs/', 'packaging_run' => '/packaging-runs/', 'removal' => '/removals/', 'return' => '/removals/'];

/** Display-unit kind for an item class (fruit weights use the fruit unit). */
function inventory_unit_kind(?string $itemClass): string
{
    return $itemClass === 'fruit' ? 'fruit' : 'default';
}

/** On hand by item, lot and location (app.v_lot_balances). Filters: item_class, location_id. */
function find_inventory_balances(PDO $pdo, string $search = '', array $filters = [], string $sort = 'item_name', int $page = 1): array
{
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(item_name ILIKE :s OR item_code ILIKE :s OR lot_number ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    if (!empty($filters['item_class'])) { $where[] = 'item_class = :ic'; $params['ic'] = $filters['item_class']; }
    if (!empty($filters['location_id'])) { $where[] = 'location_id = :loc'; $params['loc'] = (int) $filters['location_id']; }
    if (!empty($filters['item_id'])) { $where[] = 'item_id = :item'; $params['item'] = (int) $filters['item_id']; }
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    return paged_query($pdo,
        'SELECT * FROM app.v_lot_balances' . $whereSql . ' ORDER BY ' . order_by($sort, INVENTORY_BALANCE_SORTS, 'item_name') . ', item_name, lot_number, location_name',
        'SELECT count(*) FROM app.v_lot_balances' . $whereSql, $params, $page, INVENTORY_PAGE_SIZE);
}

const INVENTORY_MOVEMENT_FROM = <<<'SQL'
     FROM app.inventory_transactions t
     JOIN app.items i ON i.id = t.item_id
     JOIN app.lots l ON l.id = t.lot_id
     JOIN app.locations loc ON loc.id = t.location_id
     LEFT JOIN app.reason_codes rc ON rc.id = t.reason_code_id
     LEFT JOIN app.users u ON u.id = t.actor_id
SQL;

function inventory_movement_where(array $filters, array &$params): string
{
    $where = [];
    foreach (['item_id' => 't.item_id', 'lot_id' => 't.lot_id', 'location_id' => 't.location_id'] as $key => $column) {
        if (!empty($filters[$key])) { $where[] = $column . ' = :' . $key; $params[$key] = (int) $filters[$key]; }
    }
    if (!empty($filters['txn_type'])) { $where[] = 't.txn_type = :txn_type'; $params['txn_type'] = $filters['txn_type']; }
    if (!empty($filters['date_from'])) { $where[] = 't.occurred_at >= :date_from::date'; $params['date_from'] = $filters['date_from']; }
    if (!empty($filters['date_to'])) { $where[] = "t.occurred_at < (:date_to::date + 1)"; $params['date_to'] = $filters['date_to']; }
    return $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
}

/** Ledger history. Filters: item_id, lot_id, location_id, txn_type, date_from, date_to (Y-m-d). */
function find_inventory_movements(PDO $pdo, array $filters = [], string $sort = '-occurred_at', int $page = 1): array
{
    $params = [];
    $whereSql = inventory_movement_where($filters, $params);
    return paged_query($pdo,
        'SELECT t.id, t.group_id, t.txn_type, t.occurred_at, t.qty_base, t.reference_kind, t.reference_id, t.note, t.item_id, i.name AS item_name, i.base_unit_code, i.item_class,
                t.lot_id, l.lot_number, t.location_id, loc.name AS location_name, rc.code AS reason_code, u.display_name AS actor_name'
            . INVENTORY_MOVEMENT_FROM . $whereSql . ' ORDER BY ' . order_by($sort, INVENTORY_MOVEMENT_SORTS, '-occurred_at') . ', t.id DESC',
        'SELECT count(*)' . INVENTORY_MOVEMENT_FROM . $whereSql, $params, $page, INVENTORY_PAGE_SIZE);
}

/** Newest-first ledger rows of one lot, for the lot detail Movements tab. */
function find_lot_movements(PDO $pdo, int $lotId, int $limit = 200): array
{
    $statement = $pdo->prepare('SELECT t.id, t.group_id, t.txn_type, t.occurred_at, t.qty_base, t.reference_kind, t.reference_id, i.base_unit_code, i.item_class,
            loc.name AS location_name, rc.code AS reason_code, u.display_name AS actor_name'
        . INVENTORY_MOVEMENT_FROM . ' WHERE t.lot_id = :lot ORDER BY t.occurred_at DESC, t.id DESC LIMIT ' . (int) $limit);
    $statement->execute(['lot' => $lotId]);
    return $statement->fetchAll();
}

/** Items below their reorder point (app.v_item_stock). */
function find_reorder_items(PDO $pdo, ?string $itemClass = null, string $sort = 'name', int $page = 1): array
{
    $where = ['below_reorder_point'];
    $params = [];
    if ($itemClass) { $where[] = 'item_class = :ic'; $params['ic'] = $itemClass; }
    $whereSql = ' WHERE ' . implode(' AND ', $where);
    return paged_query($pdo,
        'SELECT *, (reorder_point_base - qty_available - qty_on_order) AS shortfall FROM app.v_item_stock' . $whereSql . ' ORDER BY ' . order_by($sort, INVENTORY_REORDER_SORTS, 'name'),
        'SELECT count(*) FROM app.v_item_stock' . $whereSql, $params, $page, INVENTORY_PAGE_SIZE);
}

function find_item_stock(PDO $pdo, int $itemId): ?array
{
    $statement = $pdo->prepare('SELECT * FROM app.v_item_stock WHERE item_id = :id');
    $statement->execute(['id' => $itemId]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** One balance row (item, lot, location) with availability; null when none exists. */
function find_balance(PDO $pdo, int $itemId, int $lotId, int $locationId): ?array
{
    $statement = $pdo->prepare('SELECT * FROM app.v_lot_balances WHERE item_id = :i AND lot_id = :l AND location_id = :loc');
    $statement->execute(['i' => $itemId, 'l' => $lotId, 'loc' => $locationId]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** Balances of one item by lot and location, for the item detail Stock tab. */
function find_item_balances(PDO $pdo, int $itemId): array
{
    $statement = $pdo->prepare('SELECT * FROM app.v_lot_balances WHERE item_id = :id ORDER BY lot_number, location_name');
    $statement->execute(['id' => $itemId]);
    return $statement->fetchAll();
}

/** Active locations keyed by id: [id, name, tax_state, premises_id, allow_negative]. */
function inventory_locations(PDO $pdo): array
{
    $rows = $pdo->query('SELECT id, name, tax_state, premises_id, allow_negative FROM app.locations WHERE active ORDER BY name')->fetchAll();
    $byId = [];
    foreach ($rows as $row) {
        $byId[(int) $row['id']] = $row;
    }
    return $byId;
}

/** id => "Name (Bonded)" for a location list, optionally limited to one tax state. */
function inventory_location_options(array $locations, ?string $taxState = null, ?int $excludeId = null): array
{
    $options = [];
    foreach ($locations as $id => $location) {
        if ($taxState !== null && $location['tax_state'] !== $taxState) { continue; }
        if ($excludeId !== null && $id === $excludeId) { continue; }
        $options[$id] = $location['name'] . ' (' . humanize($location['tax_state']) . ')';
    }
    return $options;
}

/** Active reason codes for one applies_to value, id => "CODE – Name". */
function inventory_reason_options(PDO $pdo, string $appliesTo): array
{
    $statement = $pdo->prepare('SELECT id, code || \' – \' || name AS label FROM app.reason_codes WHERE active AND applies_to = :a ORDER BY code');
    $statement->execute(['a' => $appliesTo]);
    return array_column($statement->fetchAll(), 'label', 'id');
}

function find_reason_code_by_code(PDO $pdo, string $code): ?array
{
    $statement = $pdo->prepare('SELECT * FROM app.reason_codes WHERE code = :c');
    $statement->execute(['c' => $code]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** Item facts the document forms need (base unit, class, display kind), keyed by item id. */
function inventory_item_facts(PDO $pdo, array $itemIds): array
{
    $itemIds = array_values(array_unique(array_map('intval', $itemIds)));
    if ($itemIds === []) {
        return [];
    }
    $statement = $pdo->query('SELECT id, code, name, item_class, base_unit_code FROM app.items WHERE id IN (' . implode(',', $itemIds) . ')');
    $facts = [];
    foreach ($statement->fetchAll() as $row) {
        $row['kind'] = inventory_unit_kind($row['item_class']);
        $facts[(int) $row['id']] = $row;
    }
    return $facts;
}

/** Resolve exact-match prefill names to ids: ['item' => code, 'lot_number' => number, 'location' => name]. */
function inventory_resolve_prefill(PDO $pdo, string $itemCode, string $lotNumber, string $locationName): array
{
    $find = static function (string $sql, string $value) use ($pdo): ?int {
        if ($value === '') { return null; }
        $statement = $pdo->prepare($sql);
        $statement->execute(['v' => $value]);
        $id = $statement->fetchColumn();
        return $id === false ? null : (int) $id;
    };
    return [
        'item_id' => $find('SELECT id FROM app.items WHERE lower(code) = lower(:v)', $itemCode),
        'lot_id' => $find('SELECT id FROM app.lots WHERE lower(lot_number) = lower(:v)', $lotNumber),
        'location_id' => $find('SELECT id FROM app.locations WHERE lower(name) = lower(:v)', $locationName),
    ];
}

/** Items that have available stock at a location, id => "CODE — Name". */
function inventory_items_at_location(PDO $pdo, int $locationId, bool $releasedOnly = true): array
{
    $statement = $pdo->prepare('SELECT DISTINCT item_id, item_code, item_name FROM app.v_lot_balances WHERE location_id = :loc AND qty_available > 0'
        . ($releasedOnly ? " AND quality_status = 'released'" : '') . ' ORDER BY item_code');
    $statement->execute(['loc' => $locationId]);
    $options = [];
    foreach ($statement->fetchAll() as $row) {
        $options[(int) $row['item_id']] = $row['item_code'] . ' — ' . $row['item_name'];
    }
    return $options;
}
