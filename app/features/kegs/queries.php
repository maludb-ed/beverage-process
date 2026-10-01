<?php
declare(strict_types=1);

const KEG_STATES = [
    'empty' => 'Empty', 'filled' => 'Filled', 'at_customer' => 'At customer', 'returned_dirty' => 'Returned dirty',
    'cleaning' => 'Cleaning', 'lost' => 'Lost', 'out_of_service' => 'Out of service',
];
const KEG_OWNERSHIPS = ['owned' => 'Owned', 'rented' => 'Rented', 'customer_owned' => 'Customer owned'];
const KEG_SORTS = ['serial' => 'kf.serial', 'state' => 'kf.state', 'last_moved_at' => 'kf.last_moved_at', 'days_since_moved' => 'kf.days_since_moved'];
const KEG_PAGE_SIZE = 50;
/** event => states it may start from. */
const KEG_TRANSITIONS = [
    'fill' => ['empty'],
    'return' => ['at_customer', 'filled'],
    'clean' => ['returned_dirty'],
    'mark_lost' => ['empty', 'filled', 'at_customer', 'returned_dirty', 'cleaning'],
    'found' => ['lost'],
    'retire' => ['empty', 'returned_dirty', 'lost'],
];
const KEG_EVENT_LABELS = ['fill' => 'Fill', 'ship' => 'Ship', 'return' => 'Return', 'clean' => 'Clean', 'mark_lost' => 'Mark lost', 'found' => 'Found', 'retire' => 'Retire',
    'deposit_collected' => 'Deposit collected', 'deposit_refunded' => 'Deposit refunded'];

/** Fleet rows from v_keg_fleet. Filters: state, customer_id (holder), older_than_days (days since last moved). */
function find_kegs(PDO $pdo, string $search = '', array $filters = [], string $sort = 'serial', int $page = 1): array
{
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(kf.serial ILIKE :s OR kf.lot_number ILIKE :s OR kf.holder_name ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    if (!empty($filters['state'])) { $where[] = 'kf.state = :state'; $params['state'] = (string) $filters['state']; }
    if (!empty($filters['customer_id'])) { $where[] = "kf.current_holder_kind = 'customer' AND kf.current_holder_id = :cust"; $params['cust'] = (int) $filters['customer_id']; }
    if (!empty($filters['older_than_days'])) { $where[] = 'kf.days_since_moved >= :days'; $params['days'] = (int) $filters['older_than_days']; }
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    return paged_query($pdo, 'SELECT kf.*, kf.keg_id AS id FROM app.v_keg_fleet kf' . $whereSql . ' ORDER BY ' . order_by($sort, KEG_SORTS, 'serial') . ', kf.keg_id',
        'SELECT count(*) FROM app.v_keg_fleet kf' . $whereSql, $params, $page, KEG_PAGE_SIZE);
}

function find_keg(PDO $pdo, int $id, bool $lock = false): ?array
{
    $statement = $pdo->prepare('SELECT k.*, kf.lot_number, kf.holder_name, kf.days_since_moved FROM app.kegs k JOIN app.v_keg_fleet kf ON kf.keg_id = k.id WHERE k.id = :id' . ($lock ? ' FOR UPDATE OF k' : ''));
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function find_keg_by_serial(PDO $pdo, string $serial): ?array
{
    $statement = $pdo->prepare('SELECT id FROM app.kegs WHERE lower(serial) = lower(:s)');
    $statement->execute(['s' => trim($serial)]);
    $id = $statement->fetchColumn();
    return $id === false ? null : find_keg($pdo, (int) $id);
}

function find_keg_movements(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT m.*, l.lot_number, c.name AS customer_name, loc.name AS location_name, u.display_name AS actor_name
        FROM app.keg_movements m
        LEFT JOIN app.lots l ON l.id = m.lot_id
        LEFT JOIN app.customers c ON c.id = m.customer_id
        LEFT JOIN app.locations loc ON loc.id = m.location_id
        LEFT JOIN app.users u ON u.id = m.actor_id
        WHERE m.keg_id = :id ORDER BY m.occurred_at DESC, m.id DESC
    SQL);
    $statement->execute(['id' => $id]);
    return $statement->fetchAll();
}

/** Count of kegs per state, every state present. */
function find_keg_state_counts(PDO $pdo): array
{
    $counts = array_fill_keys(array_keys(KEG_STATES), 0);
    foreach ($pdo->query('SELECT state, count(*) AS n FROM app.kegs GROUP BY state')->fetchAll() as $row) {
        $counts[$row['state']] = (int) $row['n'];
    }
    return $counts;
}

/** The first active packaged-goods location; null when none exists. */
function keg_default_location_id(PDO $pdo): ?int
{
    $id = $pdo->query("SELECT id FROM app.locations WHERE active AND kind = 'packaged_goods' ORDER BY id LIMIT 1")->fetchColumn();
    return $id === false ? null : (int) $id;
}

function keg_customer_options(PDO $pdo): array
{
    return array_column($pdo->query('SELECT id, name FROM app.customers WHERE active ORDER BY name')->fetchAll(), 'name', 'id');
}

/** Keg lots with fillable units (units available minus kegs already filled from the lot), lot id => "L-261001-001 — Product, Package — 20 available". */
function keg_fill_lot_options(PDO $pdo): array
{
    $rows = $pdo->query(<<<'SQL'
        SELECT fs.lot_id, fs.lot_number, fs.product_name, fs.package_name, sum(fs.units_available) - (SELECT count(*) FROM app.kegs k WHERE k.current_lot_id = fs.lot_id AND k.state = 'filled') AS available
        FROM app.v_finished_stock fs WHERE fs.package_kind = 'keg'
        GROUP BY fs.lot_id, fs.lot_number, fs.product_name, fs.package_name, fs.packaged_on
        HAVING sum(fs.units_available) - (SELECT count(*) FROM app.kegs k WHERE k.current_lot_id = fs.lot_id AND k.state = 'filled') > 0 ORDER BY fs.packaged_on DESC, fs.lot_number
    SQL)->fetchAll();
    $options = [];
    foreach ($rows as $row) {
        $options[(int) $row['lot_id']] = $row['lot_number'] . ' — ' . $row['product_name'] . ', ' . $row['package_name'] . ' — ' . number_format((float) $row['available']) . ' available';
    }
    return $options;
}

function insert_keg(PDO $pdo, string $serial, float $sizeL, string $ownership, float $depositAmount, ?string $notes, int $holderLocationId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.kegs (serial, size_l, ownership, deposit_amount, notes, current_holder_kind, current_holder_id)
        VALUES (:serial, :size, :ownership, :deposit, :notes, 'location', :loc)
        RETURNING id, serial, size_l, ownership, deposit_amount, state, notes
    SQL);
    $statement->execute(['serial' => $serial, 'size' => round($sizeL, 3), 'ownership' => $ownership, 'deposit' => $depositAmount, 'notes' => $notes, 'loc' => $holderLocationId]);
    return $statement->fetch();
}

function update_keg(PDO $pdo, int $id, string $serial, float $sizeL, string $ownership, float $depositAmount, ?string $notes): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.kegs SET serial = :serial, size_l = :size, ownership = :ownership, deposit_amount = :deposit, notes = :notes
        WHERE id = :id RETURNING id, serial, size_l, ownership, deposit_amount, state, notes
    SQL);
    $statement->execute(['id' => $id, 'serial' => $serial, 'size' => round($sizeL, 3), 'ownership' => $ownership, 'deposit' => $depositAmount, 'notes' => $notes]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Keg not found.');
    }
    return $row;
}

/**
 * Apply one state change (fill, return, clean, mark_lost, found, retire) and write its movement row. Checks the
 * allowed-from table; the caller owns the transaction. Returns the keg's new row.
 */
function transition_keg(PDO $pdo, int $id, string $event, ?int $lotId, ?int $customerId, ?int $locationId, int $actorId, ?string $note): array
{
    if (!isset(KEG_TRANSITIONS[$event])) {
        throw new RuntimeException('Unknown keg event.');
    }
    $keg = find_keg($pdo, $id, true) ?? throw new RuntimeException('Keg not found.');
    if (!in_array($keg['state'], KEG_TRANSITIONS[$event], true)) {
        $verb = ['fill' => 'filled', 'return' => 'returned', 'clean' => 'cleaned', 'mark_lost' => 'marked lost', 'found' => 'marked found', 'retire' => 'retired'][$event];
        throw new RuntimeException('Keg ' . $keg['serial'] . ' is ' . strtolower(KEG_STATES[$keg['state']]) . ' and cannot be ' . $verb . '.');
    }
    $sets = [];
    $params = ['id' => $id];
    switch ($event) {
        case 'fill':
            if ($lotId === null) { throw new RuntimeException('Choose the finished lot to fill from.'); }
            $sets = ["state = 'filled'", 'current_lot_id = :lot', 'fill_count = fill_count + 1', 'last_moved_at = now()'];
            $params['lot'] = $lotId;
            break;
        case 'return':
        case 'found':
            if ($locationId === null) { throw new RuntimeException('There is no packaged goods location to hold returned kegs. Add one under Locations.'); }
            $sets = ["state = 'returned_dirty'", "current_holder_kind = 'location'", 'current_holder_id = :loc', 'last_moved_at = now()'];
            if ($event === 'return') { $sets[] = 'current_lot_id = NULL'; }
            $params['loc'] = $locationId;
            break;
        case 'clean':
            $sets = ["state = 'empty'", 'last_cleaned_at = now()'];
            break;
        case 'mark_lost':
            $sets = ["state = 'lost'", "current_holder_kind = 'unknown'", 'current_holder_id = NULL', 'last_moved_at = now()'];
            break;
        case 'retire':
            $sets = ["state = 'out_of_service'"];
            break;
    }
    $pdo->prepare('UPDATE app.kegs SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
    $pdo->prepare('INSERT INTO app.keg_movements (keg_id, event, lot_id, customer_id, location_id, actor_id, note) VALUES (:keg, :event, :lot, :cust, :loc, :actor, :note)')
        ->execute(['keg' => $id, 'event' => $event, 'lot' => $event === 'fill' ? $lotId : null, 'cust' => $customerId, 'loc' => in_array($event, ['return', 'found'], true) ? $locationId : null,
            'actor' => $actorId, 'note' => $note]);
    return find_keg($pdo, $id) ?? throw new RuntimeException('Keg not found.');
}

/**
 * Return kegs typed or scanned by serial. A serial that is unknown or in the wrong state is reported and skipped; the rest are returned.
 * The caller owns the transaction.
 * Result per serial: ['serial', 'result' => returned|not_found|wrong_state, 'message', 'keg_id'].
 */
function return_kegs_by_serial(PDO $pdo, array $serials, ?int $customerId, int $actorId): array
{
    $locationId = keg_default_location_id($pdo);
    $results = [];
    $seen = [];
    foreach ($serials as $serial) {
        $serial = trim((string) $serial);
        if ($serial === '' || isset($seen[mb_strtolower($serial)])) {
            continue;
        }
        $seen[mb_strtolower($serial)] = true;
        $keg = find_keg_by_serial($pdo, $serial);
        if ($keg === null) {
            $results[] = ['serial' => $serial, 'result' => 'not_found', 'message' => 'No keg has this serial.', 'keg_id' => null];
            continue;
        }
        if (!in_array($keg['state'], KEG_TRANSITIONS['return'], true)) {
            $results[] = ['serial' => $keg['serial'], 'result' => 'wrong_state', 'message' => 'Keg is ' . strtolower(KEG_STATES[$keg['state']]) . '; only filled or at-customer kegs can be returned.', 'keg_id' => (int) $keg['id']];
            continue;
        }
        transition_keg($pdo, (int) $keg['id'], 'return', null, $customerId, $locationId, $actorId, null);
        $results[] = ['serial' => $keg['serial'], 'result' => 'returned', 'message' => 'Returned dirty.', 'keg_id' => (int) $keg['id']];
    }
    return $results;
}

/** Only a keg that was never filled and has no movements. */
function delete_keg(PDO $pdo, int $id): bool
{
    $statement = $pdo->prepare('DELETE FROM app.kegs k WHERE k.id = :id AND k.fill_count = 0 AND NOT EXISTS (SELECT 1 FROM app.keg_movements m WHERE m.keg_id = k.id)');
    $statement->execute(['id' => $id]);
    return $statement->rowCount() === 1;
}

/** Can this keg be deleted (never filled, no movements)? */
function keg_deletable(PDO $pdo, int $id): bool
{
    $statement = $pdo->prepare('SELECT k.fill_count = 0 AND NOT EXISTS (SELECT 1 FROM app.keg_movements m WHERE m.keg_id = k.id) FROM app.kegs k WHERE k.id = :id');
    $statement->execute(['id' => $id]);
    return (bool) $statement->fetchColumn();
}
