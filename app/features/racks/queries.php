<?php
declare(strict_types=1);

// Racks are app.locations rows with parent_location_id (their area) and rack_number.
// The database copies premises, kind and tax state from the area and names the rack
// "Rack <number>" (db/015_racks.sql), so racks work everywhere a location does.

const RACK_COLUMNS = 'r.id, r.premises_id, r.parent_location_id, r.rack_number, r.name, r.kind, r.tax_state, r.active, r.created_at, r.updated_at';

function find_rack(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT ' . RACK_COLUMNS . ', a.name AS area_name FROM app.locations r JOIN app.locations a ON a.id = r.parent_location_id WHERE r.id = :id');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** Areas that can hold racks: active locations that are not racks, id => "Premises — Area" (premises only when several). */
function rack_area_options(PDO $pdo, ?int $premisesId = null): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT l.id, l.name, p.name AS premises_name FROM app.locations l JOIN app.premises p ON p.id = l.premises_id
        WHERE l.active AND l.parent_location_id IS NULL AND l.kind <> 'outside' AND (:p::bigint IS NULL OR l.premises_id = :p::bigint)
        ORDER BY p.name, l.name
    SQL);
    $statement->execute(['p' => $premisesId]);
    $rows = $statement->fetchAll();
    $multi = count(array_unique(array_column($rows, 'premises_name'))) > 1;
    $options = [];
    foreach ($rows as $row) {
        $options[(int) $row['id']] = $multi ? $row['premises_name'] . ' — ' . $row['name'] : $row['name'];
    }
    return $options;
}

/** Areas that have at least one rack, id => name (filter for the board). */
function rack_board_area_options(PDO $pdo, ?int $premisesId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT DISTINCT a.id, a.name FROM app.locations r JOIN app.locations a ON a.id = r.parent_location_id
        WHERE (:p::bigint IS NULL OR r.premises_id = :p::bigint) ORDER BY a.name
    SQL);
    $statement->execute(['p' => $premisesId]);
    return array_column($statement->fetchAll(), 'name', 'id');
}

/**
 * The rack board: every rack (active, or holding stock) of the chosen premises and area,
 * in rack-number order, each with its lots oldest first. Stock lying in an area that has
 * racks but not on a rack comes back as a pseudo-rack with id null ("Not on a rack").
 * A search on product, item, lot, batch or rack number keeps only the racks that match.
 * Returns a list of ['rack' => row, 'lots' => rows].
 */
function find_rack_board(PDO $pdo, ?int $premisesId, ?int $areaId, string $search = ''): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT r.id, r.rack_number, r.name, r.active, r.premises_id, r.parent_location_id AS area_location_id, a.name AS area_name,
               app.rack_sort_key(r.rack_number) AS rack_sort
        FROM app.locations r JOIN app.locations a ON a.id = r.parent_location_id
        WHERE (:p::bigint IS NULL OR r.premises_id = :p::bigint) AND (:a::bigint IS NULL OR r.parent_location_id = :a::bigint)
          AND (r.active OR EXISTS (SELECT 1 FROM app.inventory_balances b WHERE b.location_id = r.id AND b.qty_on_hand <> 0))
        ORDER BY a.name, rack_sort
    SQL);
    $statement->execute(['p' => $premisesId, 'a' => $areaId]);
    $racks = $statement->fetchAll();
    if ($racks === []) {
        return [];
    }

    $lotStatement = $pdo->prepare(<<<'SQL'
        SELECT fs.* FROM app.v_fifo_stock fs
        WHERE (fs.rack_number IS NOT NULL OR EXISTS (SELECT 1 FROM app.locations r WHERE r.parent_location_id = fs.location_id))
          AND (:p::bigint IS NULL OR fs.premises_id = :p::bigint) AND (:a::bigint IS NULL OR fs.area_location_id = :a::bigint)
        ORDER BY fs.stock_date NULLS LAST, fs.lot_number
    SQL);
    $lotStatement->execute(['p' => $premisesId, 'a' => $areaId]);
    $lotsByLocation = [];
    foreach ($lotStatement->fetchAll() as $lot) {
        $lotsByLocation[(int) $lot['location_id']][] = $lot;
    }

    $board = [];
    $areasSeen = [];
    foreach ($racks as $rack) {
        $areaKey = (int) $rack['area_location_id'];
        if (!isset($areasSeen[$areaKey]) && !empty($lotsByLocation[$areaKey])) {
            // Loose stock in the area goes first so it is noticed and put away.
            $board[] = ['rack' => ['id' => null, 'rack_number' => null, 'name' => 'Not on a rack', 'active' => true,
                'area_location_id' => $areaKey, 'area_name' => $rack['area_name']], 'lots' => $lotsByLocation[$areaKey]];
        }
        $areasSeen[$areaKey] = true;
        $board[] = ['rack' => $rack, 'lots' => $lotsByLocation[(int) $rack['id']] ?? []];
    }

    $needle = mb_strtolower(trim($search));
    if ($needle === '') {
        return $board;
    }
    $matches = static function (array $values) use ($needle): bool {
        foreach ($values as $value) {
            if ($value !== null && str_contains(mb_strtolower((string) $value), $needle)) {
                return true;
            }
        }
        return false;
    };
    $filtered = [];
    foreach ($board as $cell) {
        if ($matches([$cell['rack']['rack_number'], $cell['rack']['name']])) {
            $filtered[] = $cell;
            continue;
        }
        $lots = array_values(array_filter($cell['lots'], static fn(array $lot): bool =>
            $matches([$lot['product_name'], $lot['package_name'], $lot['item_name'], $lot['item_code'], $lot['lot_number'], $lot['batch_number']])));
        if ($lots !== []) {
            $filtered[] = ['rack' => $cell['rack'], 'lots' => $lots];
        }
    }
    return $filtered;
}

/**
 * FIFO pick order: released stock of each item, oldest lot first, with the rack (or area)
 * it is on. Filters: premises, search (product, item, lot, batch), item_class.
 */
function find_fifo_picks(PDO $pdo, ?int $premisesId, string $search = '', ?string $itemClass = null): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT fs.* FROM app.v_fifo_stock fs
        WHERE fs.fifo_rank IS NOT NULL AND fs.qty_available > 0
          AND (:p::bigint IS NULL OR fs.premises_id = :p::bigint)
          AND (:c::text IS NULL OR fs.item_class = :c::text)
          AND (:s::text IS NULL OR fs.product_name ILIKE :s OR fs.item_name ILIKE :s OR fs.item_code ILIKE :s
               OR fs.lot_number ILIKE :s OR fs.batch_number ILIKE :s OR fs.package_name ILIKE :s)
        ORDER BY COALESCE(fs.product_name, fs.item_name), fs.package_name NULLS FIRST, fs.item_id, fs.fifo_rank, fs.area_name, fs.rack_sort NULLS FIRST
        LIMIT 500
    SQL);
    $statement->execute(['p' => $premisesId, 'c' => $itemClass, 's' => trim($search) === '' ? null : '%' . trim($search) . '%']);
    $groups = [];
    foreach ($statement->fetchAll() as $row) {
        $key = (int) $row['item_id'];
        $groups[$key] ??= ['item_id' => $key, 'label' => $row['product_name'] !== null ? $row['product_name'] . ' — ' . $row['package_name'] : $row['item_name'],
            'item_code' => $row['item_code'], 'base_unit_code' => $row['base_unit_code'], 'rows' => []];
        $groups[$key]['rows'][] = $row;
    }
    return array_values($groups);
}

/** True when the rack holds any stock. */
function rack_has_stock(PDO $pdo, int $id): bool
{
    $statement = $pdo->prepare('SELECT EXISTS (SELECT 1 FROM app.inventory_balances WHERE location_id = :id AND qty_on_hand <> 0)');
    $statement->execute(['id' => $id]);
    return (bool) $statement->fetchColumn();
}

/** Insert a rack; premises, kind, tax state and name come from the area (database trigger). */
function insert_rack(PDO $pdo, int $areaId, string $rackNumber, bool $active): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.locations (premises_id, name, kind, tax_state, parent_location_id, rack_number, active)
        SELECT a.premises_id, '', a.kind, a.tax_state, a.id, :rack, :active FROM app.locations a WHERE a.id = :area
        RETURNING id, premises_id, parent_location_id, rack_number, name, kind, tax_state, active
    SQL);
    $statement->execute(['area' => $areaId, 'rack' => $rackNumber, 'active' => $active ? 't' : 'f']);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Area not found.');
    }
    return $row;
}

function update_rack(PDO $pdo, int $id, int $areaId, string $rackNumber, bool $active): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.locations SET parent_location_id = :area, rack_number = :rack, active = :active
        WHERE id = :id AND parent_location_id IS NOT NULL
        RETURNING id, premises_id, parent_location_id, rack_number, name, kind, tax_state, active
    SQL);
    $statement->execute(['id' => $id, 'area' => $areaId, 'rack' => $rackNumber, 'active' => $active ? 't' : 'f']);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Rack not found.');
    }
    return $row;
}
