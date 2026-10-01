<?php
declare(strict_types=1);

const LOCATION_KINDS = ['receiving' => 'Receiving', 'dry_store' => 'Dry store', 'cold_room' => 'Cold room', 'freezer' => 'Freezer', 'cellar' => 'Cellar', 'packaged_goods' => 'Packaged goods', 'taproom' => 'Taproom', 'outside' => 'Outside'];
const LOCATION_TAX_STATES = ['bonded' => 'Bonded', 'tax_paid' => 'Tax paid'];
const LOCATION_SORTS = ['name' => 'l.name', 'kind' => 'l.kind', 'tax_state' => 'l.tax_state'];

const LOCATION_COLUMNS = 'l.id, l.premises_id, l.name, l.kind, l.tax_state, l.allow_negative, l.active, l.created_at, l.updated_at';

function find_locations(PDO $pdo, string $search = '', string $sort = 'name', int $page = 1, ?int $premisesId = null): array
{
    $conditions = [];
    $params = [];
    if ($search !== '') {
        $conditions[] = 'l.name ILIKE :s';
        $params['s'] = '%' . $search . '%';
    }
    if ($premisesId !== null) {
        $conditions[] = 'l.premises_id = :premises_id';
        $params['premises_id'] = $premisesId;
    }
    $where = $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
    return paged_query(
        $pdo,
        'SELECT ' . LOCATION_COLUMNS . ', p.name AS premises_name FROM app.locations l JOIN app.premises p ON p.id = l.premises_id' . $where . ' ORDER BY ' . order_by($sort, LOCATION_SORTS, 'name') . ', l.id',
        'SELECT count(*) FROM app.locations l' . $where,
        $params,
        $page
    );
}

function find_location(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT ' . LOCATION_COLUMNS . ' FROM app.locations l WHERE l.id = :id');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** True when any inventory balance at the location is non-zero. */
function location_has_stock(PDO $pdo, int $id): bool
{
    $statement = $pdo->prepare('SELECT EXISTS (SELECT 1 FROM app.inventory_balances WHERE location_id = :id AND qty_on_hand <> 0)');
    $statement->execute(['id' => $id]);
    return (bool) $statement->fetchColumn();
}

function insert_location(PDO $pdo, int $premisesId, string $name, string $kind, string $taxState, bool $allowNegative, bool $active): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.locations (premises_id, name, kind, tax_state, allow_negative, active)
        VALUES (:premises_id, :name, :kind, :tax_state, :allow_negative, :active)
        RETURNING id, premises_id, name, kind, tax_state, allow_negative, active
    SQL);
    $statement->execute(['premises_id' => $premisesId, 'name' => $name, 'kind' => $kind, 'tax_state' => $taxState,
        'allow_negative' => $allowNegative ? 't' : 'f', 'active' => $active ? 't' : 'f']);
    return $statement->fetch();
}

function update_location(PDO $pdo, int $id, int $premisesId, string $name, string $kind, string $taxState, bool $allowNegative, bool $active): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.locations
        SET premises_id = :premises_id, name = :name, kind = :kind, tax_state = :tax_state, allow_negative = :allow_negative, active = :active
        WHERE id = :id
        RETURNING id, premises_id, name, kind, tax_state, allow_negative, active
    SQL);
    $statement->execute(['id' => $id, 'premises_id' => $premisesId, 'name' => $name, 'kind' => $kind, 'tax_state' => $taxState,
        'allow_negative' => $allowNegative ? 't' : 'f', 'active' => $active ? 't' : 'f']);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Location not found.');
    }
    return $row;
}
