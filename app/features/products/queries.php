<?php
declare(strict_types=1);

const PRODUCT_BEVERAGES = ['cider' => 'Cider', 'wine' => 'Wine', 'beer' => 'Beer'];
const PRODUCT_TAX_CLASSES = [
    'hard_cider' => 'Hard cider', 'still_wine' => 'Still wine', 'artificially_carbonated_wine' => 'Artificially carbonated wine',
    'sparkling_wine' => 'Sparkling wine', 'beer' => 'Beer',
];
const PRODUCT_STATUSES = ['draft' => 'Draft', 'active' => 'Active', 'retired' => 'Retired'];
const PRODUCT_SORTS = ['name' => 'p.name', 'code' => 'p.code', 'status' => 'p.status'];
const PRODUCT_COLUMNS = 'p.id, p.code, p.name, p.beverage_type, p.style, p.intended_tax_class, p.target_abv, p.target_fruit_share_pct, p.contains_other_fruit, p.contains_flavoring, p.status, p.notes, p.created_at, p.updated_at';

function find_products(PDO $pdo, string $search = '', ?string $status = null, string $sort = 'name', int $page = 1, ?string $beverage = null): array
{
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(p.name ILIKE :s OR p.code ILIKE :s OR p.style ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    if ($status !== null && $status !== '') {
        $where[] = 'p.status = :status';
        $params['status'] = $status;
    }
    if ($beverage !== null && $beverage !== '') {
        $where[] = 'p.beverage_type = :beverage';
        $params['beverage'] = $beverage;
    }
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    return paged_query(
        $pdo,
        'SELECT ' . PRODUCT_COLUMNS . ', (SELECT rv.version_no FROM app.recipe_versions rv WHERE rv.product_id = p.id AND rv.status = \'active\') AS active_version_no
         FROM app.products p' . $whereSql . ' ORDER BY ' . order_by($sort, PRODUCT_SORTS, 'name'),
        'SELECT count(*) FROM app.products p' . $whereSql,
        $params,
        $page
    );
}

/** The product header plus the rows its detail tabs show. */
function find_product(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT ' . PRODUCT_COLUMNS . ' FROM app.products p WHERE p.id = :id');
    $statement->execute(['id' => $id]);
    $product = $statement->fetch();
    if ($product === false) {
        return null;
    }
    $query = static function (string $sql) use ($pdo, $id): array {
        $statement = $pdo->prepare($sql);
        $statement->execute(['id' => $id]);
        return $statement->fetchAll();
    };
    $product['recipes'] = $query(<<<'SQL'
        SELECT rv.id, rv.version_no, rv.status, rv.target_batch_volume_l, rv.expected_total_loss_pct, rv.standard_cost_total, rv.standard_cost_per_l,
               rv.change_note, rv.activated_at, u.display_name AS activated_by_name
        FROM app.recipe_versions rv LEFT JOIN app.users u ON u.id = rv.activated_by
        WHERE rv.product_id = :id ORDER BY rv.version_no DESC
    SQL);
    $product['active_recipe'] = null;
    foreach ($product['recipes'] as $recipe) {
        if ($recipe['status'] === 'active') {
            $product['active_recipe'] = $recipe;
        }
    }
    $product['packaging'] = $query(<<<'SQL'
        SELECT pc.id, pc.name, pc.package_kind, pc.fill_volume_l, pc.units_per_case, pc.expected_loss_pct, pc.active, i.code AS item_code, i.name AS item_name
        FROM app.packaging_configurations pc JOIN app.items i ON i.id = pc.finished_item_id
        WHERE pc.product_id = :id ORDER BY pc.name
    SQL);
    $product['specs'] = $query(<<<'SQL'
        SELECT s.id, s.stage_code, st.name AS stage_name, s.measurement_type_code, mt.name AS measurement_name, mt.unit AS measurement_unit,
               s.min_value, s.max_value, s.target_value, s.active
        FROM app.specs s JOIN app.stages st ON st.code = s.stage_code JOIN app.measurement_types mt ON mt.code = s.measurement_type_code
        WHERE s.product_id = :id ORDER BY st.display_order, mt.name
    SQL);
    $product['approvals'] = $query(<<<'SQL'
        SELECT pa.id, pa.kind, pa.reference_no, pa.status, pa.approved_on, pa.expires_on, pc.name AS package_name
        FROM app.product_approvals pa LEFT JOIN app.packaging_configurations pc ON pc.id = pa.packaging_configuration_id
        WHERE pa.product_id = :id ORDER BY pa.kind, pa.id
    SQL);
    $product['batches'] = $query(<<<'SQL'
        SELECT b.id, b.number, b.status, b.current_stage_code, b.started_at
        FROM app.batches b WHERE b.product_id = :id ORDER BY b.started_at DESC LIMIT 50
    SQL);
    return $product;
}

/** Products that are not retired, as id => "name". */
function products_options(PDO $pdo, bool $includeRetired = false): array
{
    $rows = $pdo->query('SELECT id, name FROM app.products' . ($includeRetired ? '' : " WHERE status <> 'retired'") . ' ORDER BY name')->fetchAll();
    return array_column($rows, 'name', 'id');
}

function find_product_id_by_code(PDO $pdo, string $code): ?int
{
    $statement = $pdo->prepare('SELECT id FROM app.products WHERE lower(code) = lower(:c)');
    $statement->execute(['c' => $code]);
    $id = $statement->fetchColumn();
    return $id === false ? null : (int) $id;
}

/** Stages used by a beverage type as code => name, in display order. */
function product_stage_options(PDO $pdo, string $beverageType): array
{
    $statement = $pdo->prepare('SELECT code, name FROM app.stages WHERE beverage_types @> ARRAY[:b]::text[] ORDER BY display_order');
    $statement->execute(['b' => $beverageType]);
    return array_column($statement->fetchAll(), 'name', 'code');
}

function insert_product(PDO $pdo, string $code, string $name, string $beverageType, ?string $style, string $intendedTaxClass, ?float $targetAbv, ?float $fruitSharePct, bool $containsOtherFruit, bool $containsFlavoring, string $status, ?string $notes): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.products (code, name, beverage_type, style, intended_tax_class, target_abv, target_fruit_share_pct, contains_other_fruit, contains_flavoring, status, notes)
        VALUES (:code, :name, :beverage_type, :style, :intended_tax_class, :target_abv, :fruit_share, :other_fruit, :flavoring, :status, :notes)
        RETURNING id, code, name, beverage_type, style, intended_tax_class, target_abv, target_fruit_share_pct, contains_other_fruit, contains_flavoring, status, notes
    SQL);
    $statement->execute(compact('code', 'name', 'style', 'status', 'notes') + [
        'beverage_type' => $beverageType, 'intended_tax_class' => $intendedTaxClass, 'target_abv' => $targetAbv, 'fruit_share' => $fruitSharePct,
        'other_fruit' => $containsOtherFruit ? 't' : 'f', 'flavoring' => $containsFlavoring ? 't' : 'f',
    ]);
    return $statement->fetch();
}

function update_product(PDO $pdo, int $id, string $code, string $name, string $beverageType, ?string $style, string $intendedTaxClass, ?float $targetAbv, ?float $fruitSharePct, bool $containsOtherFruit, bool $containsFlavoring, string $status, ?string $notes): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.products SET code = :code, name = :name, beverage_type = :beverage_type, style = :style, intended_tax_class = :intended_tax_class,
               target_abv = :target_abv, target_fruit_share_pct = :fruit_share, contains_other_fruit = :other_fruit, contains_flavoring = :flavoring,
               status = :status, notes = :notes
        WHERE id = :id
        RETURNING id, code, name, beverage_type, style, intended_tax_class, target_abv, target_fruit_share_pct, contains_other_fruit, contains_flavoring, status, notes
    SQL);
    $statement->execute(compact('id', 'code', 'name', 'style', 'status', 'notes') + [
        'beverage_type' => $beverageType, 'intended_tax_class' => $intendedTaxClass, 'target_abv' => $targetAbv, 'fruit_share' => $fruitSharePct,
        'other_fruit' => $containsOtherFruit ? 't' : 'f', 'flavoring' => $containsFlavoring ? 't' : 'f',
    ]);
    return $statement->fetch();
}

function retire_product(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare("UPDATE app.products SET status = 'retired' WHERE id = :id RETURNING id, code, name, status");
    $statement->execute(['id' => $id]);
    return $statement->fetch();
}
