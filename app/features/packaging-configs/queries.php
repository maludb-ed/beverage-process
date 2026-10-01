<?php
declare(strict_types=1);

const PACKAGE_KINDS = ['keg' => 'Keg', 'can' => 'Can', 'bottle' => 'Bottle'];
const PACKAGING_SORTS = ['product_name' => 'p.name', 'name' => 'pc.name', 'package_kind' => 'pc.package_kind'];
const PACKAGING_FILL_UNITS = ['gal', 'L', 'floz', 'mL'];
const PACKAGING_BOM_CLASSES = ['packaging', 'consumable', 'returnable_asset'];

function find_packaging_configurations(PDO $pdo, string $search = '', array $filters = [], string $sort = 'product_name', int $page = 1): array
{
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(pc.name ILIKE :s OR p.name ILIKE :s OR i.name ILIKE :s OR i.code ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    if (!empty($filters['product_id'])) { $where[] = 'pc.product_id = :product_id'; $params['product_id'] = (int) $filters['product_id']; }
    if (!empty($filters['package_kind'])) { $where[] = 'pc.package_kind = :kind'; $params['kind'] = $filters['package_kind']; }
    if (isset($filters['active']) && $filters['active'] !== '') { $where[] = 'pc.active = :active'; $params['active'] = (bool) $filters['active']; }
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    $from = ' FROM app.packaging_configurations pc JOIN app.products p ON p.id = pc.product_id JOIN app.items i ON i.id = pc.finished_item_id';
    return paged_query(
        $pdo,
        'SELECT pc.id, pc.name, pc.package_kind, pc.fill_volume_l, pc.units_per_case, pc.expected_loss_pct, pc.active, pc.product_id, p.name AS product_name,
                i.code AS item_code, i.name AS item_name, (SELECT count(*) FROM app.packaging_bom_lines b WHERE b.configuration_id = pc.id) AS bom_count'
            . $from . $whereSql . ' ORDER BY ' . order_by($sort, PACKAGING_SORTS, 'product_name') . ', pc.name',
        'SELECT count(*)' . $from . $whereSql,
        $params,
        $page
    );
}

function find_packaging_configuration(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT pc.id, pc.product_id, pc.finished_item_id, pc.name, pc.package_kind, pc.fill_volume_l, pc.units_per_case, pc.expected_loss_pct, pc.active
        FROM app.packaging_configurations pc WHERE pc.id = :id
    SQL);
    $statement->execute(['id' => $id]);
    $config = $statement->fetch();
    if ($config === false) {
        return null;
    }
    $bom = $pdo->prepare(<<<'SQL'
        SELECT b.id, b.item_id, b.qty_per_unit_base, i.code AS item_code, i.name AS item_name, i.base_unit_code
        FROM app.packaging_bom_lines b JOIN app.items i ON i.id = b.item_id WHERE b.configuration_id = :id ORDER BY b.id
    SQL);
    $bom->execute(['id' => $id]);
    $config['bom'] = $bom->fetchAll();
    return $config;
}

/** Active finished goods as id => "code — name". */
function packaging_finished_item_options(PDO $pdo): array
{
    $rows = $pdo->query("SELECT id, code || ' — ' || name AS label FROM app.items WHERE item_class = 'finished_good' AND active ORDER BY code")->fetchAll();
    return array_column($rows, 'label', 'id');
}

/** Items usable in a BOM: id => [code, name, base_unit_code]. */
function packaging_bom_item_catalog(PDO $pdo): array
{
    $statement = $pdo->prepare('SELECT id, code, name, base_unit_code FROM app.items WHERE active AND item_class = ANY(:classes::text[]) ORDER BY code');
    $statement->execute(['classes' => '{' . implode(',', PACKAGING_BOM_CLASSES) . '}']);
    $catalog = [];
    foreach ($statement->fetchAll() as $row) {
        $catalog[(int) $row['id']] = ['code' => $row['code'], 'name' => $row['name'], 'base_unit_code' => $row['base_unit_code']];
    }
    return $catalog;
}

/** Volume units offered for the fill volume: code => name. */
function packaging_fill_unit_options(): array
{
    $options = [];
    foreach (unit_table() as $code => $unit) {
        if ($unit['dimension'] === 'volume' && in_array($code, PACKAGING_FILL_UNITS, true)) {
            $options[$code] = $code . ' (' . $unit['name'] . ')';
        }
    }
    return $options;
}

function replace_packaging_bom_lines(PDO $pdo, int $configurationId, array $bomLines): void
{
    $pdo->prepare('DELETE FROM app.packaging_bom_lines WHERE configuration_id = :id')->execute(['id' => $configurationId]);
    $insert = $pdo->prepare('INSERT INTO app.packaging_bom_lines (configuration_id, item_id, qty_per_unit_base) VALUES (:c, :i, :q)');
    foreach ($bomLines as $line) {
        $insert->execute(['c' => $configurationId, 'i' => $line['item_id'], 'q' => $line['qty_per_unit_base']]);
    }
}

function insert_packaging_configuration(PDO $pdo, int $productId, int $finishedItemId, string $name, string $packageKind, float $fillVolumeL, ?int $unitsPerCase, float $expectedLossPct, bool $active, array $bomLines): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.packaging_configurations (product_id, finished_item_id, name, package_kind, fill_volume_l, units_per_case, expected_loss_pct, active)
        VALUES (:product_id, :item_id, :name, :kind, :fill, :upc, :loss, :active)
        RETURNING id, product_id, finished_item_id, name, package_kind, fill_volume_l, units_per_case, expected_loss_pct, active
    SQL);
    $statement->execute(['product_id' => $productId, 'item_id' => $finishedItemId, 'name' => $name, 'kind' => $packageKind, 'fill' => $fillVolumeL,
        'upc' => $unitsPerCase, 'loss' => $expectedLossPct, 'active' => $active ? 't' : 'f']);
    $config = $statement->fetch();
    replace_packaging_bom_lines($pdo, (int) $config['id'], $bomLines);
    $config['bom'] = array_values($bomLines);
    return $config;
}

function update_packaging_configuration(PDO $pdo, int $id, int $productId, int $finishedItemId, string $name, string $packageKind, float $fillVolumeL, ?int $unitsPerCase, float $expectedLossPct, bool $active, array $bomLines): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.packaging_configurations SET product_id = :product_id, finished_item_id = :item_id, name = :name, package_kind = :kind,
               fill_volume_l = :fill, units_per_case = :upc, expected_loss_pct = :loss, active = :active
        WHERE id = :id
        RETURNING id, product_id, finished_item_id, name, package_kind, fill_volume_l, units_per_case, expected_loss_pct, active
    SQL);
    $statement->execute(['id' => $id, 'product_id' => $productId, 'item_id' => $finishedItemId, 'name' => $name, 'kind' => $packageKind, 'fill' => $fillVolumeL,
        'upc' => $unitsPerCase, 'loss' => $expectedLossPct, 'active' => $active ? 't' : 'f']);
    $config = $statement->fetch();
    replace_packaging_bom_lines($pdo, $id, $bomLines);
    $config['bom'] = array_values($bomLines);
    return $config;
}
