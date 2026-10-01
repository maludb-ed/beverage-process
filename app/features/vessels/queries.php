<?php
declare(strict_types=1);

const VESSEL_KINDS = ['tank' => 'Tank', 'fermenter' => 'Fermenter', 'brite' => 'Brite', 'tote' => 'Tote', 'ibc' => 'IBC', 'barrel' => 'Barrel', 'press' => 'Press'];
/** Statuses an operator may set; in_use is set only by execution handlers. */
const VESSEL_SETTABLE_STATUSES = ['empty' => 'Empty', 'cleaning' => 'Cleaning', 'out_of_service' => 'Out of service'];
const VESSEL_LOCATION_KINDS = ['cellar', 'cold_room', 'receiving', 'outside'];
const VESSEL_SORTS = ['name' => 'v.name', 'kind' => 'v.kind', 'capacity_l' => 'v.capacity_l', 'status' => 'v.status'];

const VESSEL_COLUMNS = 'v.id, v.premises_id, v.location_id, v.name, v.kind, v.capacity_l, v.status, v.notes, v.active, v.created_at, v.updated_at';

function find_vessels(PDO $pdo, string $search = '', string $sort = 'name', int $page = 1, ?int $premisesId = null): array
{
    $conditions = [];
    $params = [];
    if ($search !== '') {
        $conditions[] = 'v.name ILIKE :s';
        $params['s'] = '%' . $search . '%';
    }
    if ($premisesId !== null) {
        $conditions[] = 'v.premises_id = :premises_id';
        $params['premises_id'] = $premisesId;
    }
    $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
    return paged_query(
        $pdo,
        'SELECT ' . VESSEL_COLUMNS . ', l.name AS location_name FROM app.vessels v JOIN app.locations l ON l.id = v.location_id' . $where . ' ORDER BY ' . order_by($sort, VESSEL_SORTS, 'name') . ', v.id',
        'SELECT count(*) FROM app.vessels v' . $where,
        $params,
        $page
    );
}

function find_vessel(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT ' . VESSEL_COLUMNS . ' FROM app.vessels v WHERE v.id = :id');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** Active locations a vessel may sit in: id => ['premises_id', 'premises_name', 'name']. */
function vessel_location_choices(PDO $pdo): array
{
    $in = "'" . implode("','", VESSEL_LOCATION_KINDS) . "'";
    $rows = $pdo->query('SELECT l.id, l.premises_id, p.name AS premises_name, l.name FROM app.locations l JOIN app.premises p ON p.id = l.premises_id WHERE l.active AND l.kind IN (' . $in . ') ORDER BY p.name, l.name')->fetchAll();
    $choices = [];
    foreach ($rows as $row) {
        $choices[(int) $row['id']] = $row;
    }
    return $choices;
}

function insert_vessel(PDO $pdo, int $premisesId, int $locationId, string $name, string $kind, float $capacityL, string $status, ?string $notes, bool $active): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.vessels (premises_id, location_id, name, kind, capacity_l, status, notes, active)
        VALUES (:premises_id, :location_id, :name, :kind, :capacity_l, :status, :notes, :active)
        RETURNING id, premises_id, location_id, name, kind, capacity_l, status, notes, active
    SQL);
    $statement->execute(['premises_id' => $premisesId, 'location_id' => $locationId, 'name' => $name, 'kind' => $kind, 'capacity_l' => $capacityL,
        'status' => $status, 'notes' => $notes, 'active' => $active ? 't' : 'f']);
    return $statement->fetch();
}

function update_vessel(PDO $pdo, int $id, int $premisesId, int $locationId, string $name, string $kind, float $capacityL, string $status, ?string $notes, bool $active): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.vessels
        SET premises_id = :premises_id, location_id = :location_id, name = :name, kind = :kind, capacity_l = :capacity_l, status = :status, notes = :notes, active = :active
        WHERE id = :id
        RETURNING id, premises_id, location_id, name, kind, capacity_l, status, notes, active
    SQL);
    $statement->execute(['id' => $id, 'premises_id' => $premisesId, 'location_id' => $locationId, 'name' => $name, 'kind' => $kind, 'capacity_l' => $capacityL,
        'status' => $status, 'notes' => $notes, 'active' => $active ? 't' : 'f']);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Vessel not found.');
    }
    return $row;
}

function update_vessel_status(PDO $pdo, int $id, string $status): array
{
    $statement = $pdo->prepare('UPDATE app.vessels SET status = :status WHERE id = :id RETURNING id, name, status');
    $statement->execute(['id' => $id, 'status' => $status]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Vessel not found.');
    }
    return $row;
}
