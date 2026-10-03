<?php
declare(strict_types=1);

const STANDARD_COST_SORTS = ['name' => 'i.name', 'code' => 'i.code', 'item_class' => 'i.item_class'];
const STANDARD_COST_METHODS = ['actual_lot' => 'Actual lot', 'standard' => 'Standard'];
const STANDARD_COST_PAGE_SIZE = 50;

/** Display-unit kind for an item class (fruit weights use the fruit unit). */
function standard_cost_unit_kind(string $itemClass): string
{
    return $itemClass === 'fruit' ? 'fruit' : 'default';
}

function find_standard_cost_items(PDO $pdo, string $search = '', array $filters = [], string $sort = 'name', int $page = 1): array
{
    $where = ['i.active'];
    $params = [];
    if ($search !== '') {
        $where[] = '(i.name ILIKE :s OR i.code ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    if (!empty($filters['item_class'])) { $where[] = 'i.item_class = :item_class'; $params['item_class'] = $filters['item_class']; }
    if (!empty($filters['costing_method'])) { $where[] = 'i.costing_method = :costing_method'; $params['costing_method'] = $filters['costing_method']; }
    $whereSql = ' WHERE ' . implode(' AND ', $where);
    return paged_query(
        $pdo,
        'SELECT i.id, i.code, i.name, i.item_class, i.base_unit_code, i.costing_method, i.standard_cost_per_base,
                (SELECT max(sc.effective_from) FROM app.standard_costs sc WHERE sc.item_id = i.id) AS latest_effective_from,
                (SELECT count(*) FROM app.standard_costs sc WHERE sc.item_id = i.id) AS history_count
         FROM app.items i' . $whereSql . ' ORDER BY ' . order_by($sort, STANDARD_COST_SORTS, 'name'),
        'SELECT count(*) FROM app.items i' . $whereSql,
        $params,
        $page,
        STANDARD_COST_PAGE_SIZE
    );
}

function find_standard_cost_history(PDO $pdo, int $itemId): array
{
    $statement = $pdo->prepare('SELECT sc.id, sc.cost_per_base, sc.effective_from, sc.created_at, u.display_name AS created_by_name
        FROM app.standard_costs sc LEFT JOIN app.users u ON u.id = sc.created_by WHERE sc.item_id = :id ORDER BY sc.effective_from DESC');
    $statement->execute(['id' => $itemId]);
    return $statement->fetchAll();
}

/** Active items for the cost form: id => [code, name, item_class, base_unit_code, standard_cost_per_base]. */
function standard_cost_item_catalog(PDO $pdo): array
{
    $catalog = [];
    foreach ($pdo->query('SELECT id, code, name, item_class, base_unit_code, standard_cost_per_base FROM app.items WHERE active ORDER BY code')->fetchAll() as $row) {
        $catalog[(int) $row['id']] = $row;
    }
    return $catalog;
}

function find_standard_cost_item_id_by_code(PDO $pdo, string $code): ?int
{
    $statement = $pdo->prepare('SELECT id FROM app.items WHERE lower(code) = lower(:c)');
    $statement->execute(['c' => $code]);
    $id = $statement->fetchColumn();
    return $id === false ? null : (int) $id;
}

/**
 * Records a standard cost. When it is the latest cost dated on or before today it also becomes
 * items.standard_cost_per_base (costing_method is left unchanged). Returns the row plus 'applied' and 'previous_standard'.
 */
function insert_standard_cost(PDO $pdo, int $itemId, float $costPerBase, string $effectiveFrom, int $createdBy): array
{
    $previous = $pdo->prepare('SELECT standard_cost_per_base FROM app.items WHERE id = :id FOR UPDATE');
    $previous->execute(['id' => $itemId]);
    $previousStandard = $previous->fetchColumn();
    $statement = $pdo->prepare('INSERT INTO app.standard_costs (item_id, cost_per_base, effective_from, created_by) VALUES (:item, :cost, :from, :by)
        RETURNING id, item_id, cost_per_base, effective_from');
    $statement->execute(['item' => $itemId, 'cost' => $costPerBase, 'from' => $effectiveFrom, 'by' => $createdBy]);
    $row = $statement->fetch();
    $latest = $pdo->prepare('SELECT max(effective_from) FROM app.standard_costs WHERE item_id = :id AND effective_from <= current_date');
    $latest->execute(['id' => $itemId]);
    $row['applied'] = (string) $latest->fetchColumn() === $effectiveFrom;
    if ($row['applied']) {
        $pdo->prepare('UPDATE app.items SET standard_cost_per_base = :cost WHERE id = :id')->execute(['cost' => $costPerBase, 'id' => $itemId]);
    }
    $row['previous_standard'] = $previousStandard === false ? null : $previousStandard;
    return $row;
}

/** Active premises with their current overhead rate (latest effective on or before today), or nulls. */
function find_overhead_rates(PDO $pdo): array
{
    return $pdo->query(<<<'SQL'
        SELECT pr.id AS premises_id, pr.name AS premises_name, o.id, o.rate_per_l, o.effective_from
        FROM app.premises pr
        LEFT JOIN LATERAL (SELECT x.id, x.rate_per_l, x.effective_from FROM app.overhead_rates x
                           WHERE x.premises_id = pr.id AND x.effective_from <= current_date ORDER BY x.effective_from DESC LIMIT 1) o ON true
        WHERE pr.active ORDER BY pr.name
    SQL)->fetchAll();
}

function insert_overhead_rate(PDO $pdo, int $premisesId, float $ratePerL, string $effectiveFrom, int $createdBy): array
{
    $statement = $pdo->prepare('INSERT INTO app.overhead_rates (premises_id, rate_per_l, effective_from, created_by) VALUES (:p, :rate, :from, :by)
        RETURNING id, premises_id, rate_per_l, effective_from');
    $statement->execute(['p' => $premisesId, 'rate' => $ratePerL, 'from' => $effectiveFrom, 'by' => $createdBy]);
    return $statement->fetch();
}
