<?php
declare(strict_types=1);

require_once __DIR__ . '/../lots/queries.php';

const FINISHED_LOT_SORTS = ['packaged_on' => 'fs.packaged_on', 'lot_number' => 'fs.lot_number', 'product_name' => 'fs.product_name'];
const FINISHED_LOT_TAX_CLASSES = [
    'hard_cider' => 'Hard cider', 'still_wine' => 'Still wine', 'artificially_carbonated_wine' => 'Artificially carbonated wine', 'sparkling_wine' => 'Sparkling wine',
];
const FINISHED_LOT_PACKAGE_KINDS = ['keg' => 'Keg', 'can' => 'Can', 'bottle' => 'Bottle'];

/** Rows of v_finished_stock (one per lot and location). Filters: product_id, package_kind, location_id. */
function find_finished_lots(PDO $pdo, string $search = '', array $filters = [], string $sort = '-packaged_on', int $page = 1): array
{
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(fs.lot_number ILIKE :s OR fs.product_name ILIKE :s OR fs.batch_number ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    if (!empty($filters['product_id'])) { $where[] = 'fs.product_id = :product'; $params['product'] = (int) $filters['product_id']; }
    if (!empty($filters['package_kind'])) { $where[] = 'fs.package_kind = :kind'; $params['kind'] = (string) $filters['package_kind']; }
    if (!empty($filters['location_id'])) { $where[] = 'fs.location_id = :loc'; $params['loc'] = (int) $filters['location_id']; }
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    return paged_query($pdo, 'SELECT fs.*, fs.lot_id AS id FROM app.v_finished_stock fs' . $whereSql . ' ORDER BY ' . order_by($sort, FINISHED_LOT_SORTS, '-packaged_on') . ', fs.lot_id DESC, fs.location_name',
        'SELECT count(*) FROM app.v_finished_stock fs' . $whereSql, $params, $page);
}

function finished_lot_product_options(PDO $pdo): array
{
    return array_column($pdo->query('SELECT DISTINCT p.id, p.name FROM app.finished_lots fl JOIN app.batches b ON b.id = fl.batch_id JOIN app.products p ON p.id = b.product_id ORDER BY p.name')->fetchAll(), 'name', 'id');
}

function finished_lot_location_options(PDO $pdo): array
{
    return array_column($pdo->query("SELECT id, name FROM app.locations WHERE active AND kind IN ('packaged_goods', 'cold_room', 'cellar', 'taproom') ORDER BY name")->fetchAll(), 'name', 'id');
}

function find_finished_lot(PDO $pdo, int $lotId): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT fl.*, l.lot_number, l.quality_status, l.unit_cost_base, l.item_id, i.code AS item_code, i.name AS item_name,
               b.number AS batch_number, b.fruit_share_pct AS batch_fruit_share_pct, p.id AS product_id, p.name AS product_name, p.beverage_type,
               p.contains_other_fruit, p.contains_flavoring, pc.name AS package_name, pc.package_kind, pr.number AS run_number,
               rc.name AS override_reason_name, uo.display_name AS override_by_name, pa.reference_no AS label_reference, pa.status AS label_status,
               COALESCE((SELECT sum(b2.qty_on_hand) FROM app.inventory_balances b2 WHERE b2.lot_id = fl.lot_id), 0) AS units_on_hand
        FROM app.finished_lots fl
        JOIN app.lots l ON l.id = fl.lot_id
        JOIN app.items i ON i.id = l.item_id
        JOIN app.batches b ON b.id = fl.batch_id
        JOIN app.products p ON p.id = b.product_id
        JOIN app.packaging_configurations pc ON pc.id = fl.packaging_configuration_id
        JOIN app.packaging_runs pr ON pr.id = fl.packaging_run_id
        LEFT JOIN app.reason_codes rc ON rc.id = fl.tax_class_override_reason_code_id
        LEFT JOIN app.users uo ON uo.id = fl.tax_class_override_by
        LEFT JOIN app.product_approvals pa ON pa.id = fl.label_approval_id
        WHERE fl.lot_id = :id
    SQL);
    $statement->execute(['id' => $lotId]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function find_finished_lot_balances(PDO $pdo, int $lotId): array
{
    $statement = $pdo->prepare('SELECT * FROM app.v_lot_balances WHERE lot_id = :id ORDER BY location_name');
    $statement->execute(['id' => $lotId]);
    return $statement->fetchAll();
}

/** Removal lines (slice 10) that took units of this lot. */
function find_finished_lot_removals(PDO $pdo, int $lotId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT rl.id, rl.units, rl.volume_l, rl.tax_class, r.id AS removal_id, r.number, r.status, r.destination_kind, r.removed_at, c.name AS customer_name, k.serial AS keg_serial
        FROM app.removal_lines rl
        JOIN app.removals r ON r.id = rl.removal_id
        LEFT JOIN app.customers c ON c.id = r.customer_id
        LEFT JOIN app.kegs k ON k.id = rl.keg_id
        WHERE rl.lot_id = :id ORDER BY r.removed_at DESC, rl.id
    SQL);
    $statement->execute(['id' => $lotId]);
    return $statement->fetchAll();
}

/** Kegs that currently hold this lot. */
function find_finished_lot_kegs(PDO $pdo, int $lotId): array
{
    $statement = $pdo->prepare('SELECT * FROM app.v_keg_fleet WHERE current_lot_id = :id ORDER BY serial');
    $statement->execute(['id' => $lotId]);
    return $statement->fetchAll();
}

/** The class app.derive_tax_class gives for the lot's readings and its product's flags; null when no rule exists. */
function derive_finished_lot_tax_class(PDO $pdo, int $lotId): ?string
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT app.derive_tax_class(p.beverage_type, fl.abv, fl.co2_g_100ml, fl.fruit_share_pct, p.contains_other_fruit, p.contains_flavoring, false, fl.packaged_on)
        FROM app.finished_lots fl JOIN app.batches b ON b.id = fl.batch_id JOIN app.products p ON p.id = b.product_id WHERE fl.lot_id = :id
    SQL);
    $statement->execute(['id' => $lotId]);
    $value = $statement->fetchColumn();
    return $value === false || $value === null ? null : (string) $value;
}

/** Hard-cider limits from the tax class rules in force (27 CFR 24.331 defaults when no rule is stored). */
function finished_lot_hard_cider_limits(PDO $pdo): array
{
    $json = $pdo->query("SELECT params->'hard_cider' FROM app.tax_class_rules WHERE beverage_type = 'cider' AND effective_from <= current_date ORDER BY effective_from DESC LIMIT 1")->fetchColumn();
    $rule = $json ? (json_decode((string) $json, true) ?: []) : [];
    return [
        'co2_max' => (float) ($rule['co2_max_g_100ml'] ?? 0.64),
        'abv_max' => (float) ($rule['abv_max_exclusive'] ?? 8.5),
        'fruit_min' => (float) ($rule['fruit_share_min_pct'] ?? 50),
    ];
}

/** Override reasons, id => name, and the id of TAXOVR (the default) or null. */
function finished_lot_override_reasons(PDO $pdo): array
{
    $options = override_reason_options($pdo);
    $statement = $pdo->query("SELECT id FROM app.reason_codes WHERE code = 'TAXOVR' AND active");
    $default = $statement->fetchColumn();
    return [$options, $default === false ? null : (int) $default];
}

function override_finished_lot_tax_class(PDO $pdo, int $lotId, string $taxClass, int $reasonCodeId, int $actorId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.finished_lots SET tax_class = :tax, tax_class_source = 'override', tax_class_override_reason_code_id = :reason, tax_class_override_by = :by
        WHERE lot_id = :id RETURNING lot_id, tax_class, tax_class_source, tax_class_override_reason_code_id
    SQL);
    $statement->execute(['tax' => $taxClass, 'reason' => $reasonCodeId, 'by' => $actorId, 'id' => $lotId]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Finished lot not found.');
    }
    set_lot_attribute($pdo, $lotId, 'tax_class', null, $taxClass, null, 'manual', $actorId);
    return $row;
}
