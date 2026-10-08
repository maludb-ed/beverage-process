<?php
declare(strict_types=1);

// Equipment that holds no liquid (db/023): mills, pumps, filters, lines. A press is a vessel of kind press.

const EQUIPMENT_KINDS = ['mill' => 'Mill', 'pump' => 'Pump', 'filter' => 'Filter', 'chiller' => 'Chiller', 'carbonator' => 'Carbonator',
    'canning_line' => 'Canning line', 'bottling_line' => 'Bottling line', 'keg_line' => 'Keg line', 'keg_washer' => 'Keg washer', 'labeler' => 'Labeler', 'other' => 'Other'];
const EQUIPMENT_STATUSES = ['available' => 'Available', 'cleaning' => 'Cleaning', 'out_of_service' => 'Out of service'];

const EQUIPMENT_COLUMNS = 'e.id, e.premises_id, e.location_id, e.name, e.kind, e.status, e.rating, e.notes, e.active, e.created_at, e.updated_at';

/**
 * Every piece of equipment as cards: filtered by search, kind, status and premises; inactive last. Each row carries its
 * next booking (the first booked reservation that has not ended) and how many bookings are still to come.
 */
function find_equipment_list(PDO $pdo, string $search = '', string $kind = '', string $status = '', ?int $premisesId = null, bool $includeInactive = true): array
{
    $conditions = [];
    $params = [];
    if ($search !== '') { $conditions[] = 'e.name ILIKE :s'; $params['s'] = '%' . $search . '%'; }
    if ($kind !== '') { $conditions[] = 'e.kind = :kind'; $params['kind'] = $kind; }
    if ($status !== '') { $conditions[] = 'e.status = :status'; $params['status'] = $status; }
    if ($premisesId !== null) { $conditions[] = 'e.premises_id = :premises'; $params['premises'] = $premisesId; }
    if (!$includeInactive) { $conditions[] = 'e.active'; }
    $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
    $columns = EQUIPMENT_COLUMNS;
    $statement = $pdo->prepare(<<<SQL
        SELECT {$columns}, p.name AS premises_name, l.name AS location_name,
               nb.id AS next_reservation_id, nb.local_from AS next_from, nb.local_to AS next_to, nb.kind AS next_kind, nb.subject_number AS next_number, nb.subject_label AS next_label,
               (SELECT count(*) FROM app.v_equipment_schedule s WHERE s.resource_kind = 'equipment' AND s.resource_id = e.id AND s.ends_at > now()) AS bookings_ahead
          FROM app.equipment e
          JOIN app.premises p ON p.id = e.premises_id
          LEFT JOIN app.locations l ON l.id = e.location_id
          LEFT JOIN LATERAL (SELECT s.id, s.local_from, s.local_to, s.kind, s.subject_number, s.subject_label FROM app.v_equipment_schedule s
                              WHERE s.resource_kind = 'equipment' AND s.resource_id = e.id AND s.ends_at > now() ORDER BY s.starts_at LIMIT 1) nb ON true
         {$where}
         ORDER BY e.active DESC, p.name, e.kind, e.name
    SQL);
    $statement->execute($params);
    return $statement->fetchAll();
}

function find_equipment(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT ' . EQUIPMENT_COLUMNS . ', p.name AS premises_name, l.name AS location_name FROM app.equipment e JOIN app.premises p ON p.id = e.premises_id LEFT JOIN app.locations l ON l.id = e.location_id WHERE e.id = :id');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** Active areas (not racks) a piece of equipment may stand in: id => ['premises_id', 'premises_name', 'name']. */
function equipment_location_choices(PDO $pdo): array
{
    $rows = $pdo->query('SELECT l.id, l.premises_id, p.name AS premises_name, l.name FROM app.locations l JOIN app.premises p ON p.id = l.premises_id WHERE l.active AND l.parent_location_id IS NULL ORDER BY p.name, l.name')->fetchAll();
    $choices = [];
    foreach ($rows as $row) {
        $choices[(int) $row['id']] = $row;
    }
    return $choices;
}

function insert_equipment(PDO $pdo, int $premisesId, ?int $locationId, string $name, string $kind, string $status, ?string $rating, ?string $notes, bool $active): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.equipment (premises_id, location_id, name, kind, status, rating, notes, active)
        VALUES (:premises_id, :location_id, :name, :kind, :status, :rating, :notes, :active)
        RETURNING id, premises_id, location_id, name, kind, status, rating, notes, active
    SQL);
    $statement->execute(['premises_id' => $premisesId, 'location_id' => $locationId, 'name' => $name, 'kind' => $kind, 'status' => $status,
        'rating' => $rating, 'notes' => $notes, 'active' => $active ? 't' : 'f']);
    return $statement->fetch();
}

function update_equipment(PDO $pdo, int $id, int $premisesId, ?int $locationId, string $name, string $kind, string $status, ?string $rating, ?string $notes, bool $active): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.equipment
        SET premises_id = :premises_id, location_id = :location_id, name = :name, kind = :kind, status = :status, rating = :rating, notes = :notes, active = :active
        WHERE id = :id
        RETURNING id, premises_id, location_id, name, kind, status, rating, notes, active
    SQL);
    $statement->execute(['id' => $id, 'premises_id' => $premisesId, 'location_id' => $locationId, 'name' => $name, 'kind' => $kind, 'status' => $status,
        'rating' => $rating, 'notes' => $notes, 'active' => $active ? 't' : 'f']);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Equipment not found.');
    }
    return $row;
}

function update_equipment_status(PDO $pdo, int $id, string $status): array
{
    $statement = $pdo->prepare('UPDATE app.equipment SET status = :status WHERE id = :id RETURNING id, name, status');
    $statement->execute(['id' => $id, 'status' => $status]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Equipment not found.');
    }
    return $row;
}

/** Booked reservations of one resource: ['upcoming' => rows ending after now, 'past' => the rest], from v_equipment_schedule. */
function find_resource_bookings(PDO $pdo, string $resourceKind, int $resourceId, int $pastLimit = 25): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT s.*, (s.ends_at > now()) AS ahead
          FROM app.v_equipment_schedule s
         WHERE s.resource_kind = :kind AND s.resource_id = :id
         ORDER BY (s.ends_at > now()) DESC, CASE WHEN s.ends_at > now() THEN s.starts_at END ASC, CASE WHEN s.ends_at <= now() THEN s.starts_at END DESC
    SQL);
    $statement->execute(['kind' => $resourceKind, 'id' => $resourceId]);
    $upcoming = [];
    $past = [];
    foreach ($statement->fetchAll() as $row) {
        if ($row['ahead']) {
            $upcoming[] = $row;
        } elseif (count($past) < $pastLimit) {
            $past[] = $row;
        }
    }
    return ['upcoming' => $upcoming, 'past' => $past];
}
