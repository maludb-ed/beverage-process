<?php
declare(strict_types=1);

// Item classes live in app.item_classes (017): see item_class_options(). Built-in codes keep
// behaviour in PHP (fruit, finished_good, packaging...); custom classes behave by their flags.
const ITEM_CLASS_KINDS = ['material' => 'Material', 'finished' => 'Finished product'];
const ITEM_CLASS_CODE_PATTERN = '/^[a-z][a-z0-9_]{1,29}$/';
const ITEM_QUARANTINE_CLASSES = ['fruit', 'juice', 'yeast', 'additive'];
const ITEM_RECEIPT_STATUSES = ['quarantine' => 'Quarantine', 'released' => 'Released'];
const ITEM_CONSUMPTION_MODES = ['explicit' => 'Explicit', 'backflush' => 'Backflush'];
const ITEM_COSTING_METHODS = ['actual_lot' => 'Actual (lot cost)', 'standard' => 'Standard'];
const ITEM_TTB_CATEGORIES = ['none' => 'None', 'fruit' => 'Fruit', 'juice' => 'Juice', 'concentrate' => 'Concentrate', 'sugar' => 'Sugar', 'other' => 'Other'];
const ITEM_SORTS = ['code' => 'i.code', 'name' => 'i.name', 'item_class' => 'i.item_class'];

const ITEM_COLUMNS = 'i.id, i.code, i.name, i.item_class, i.base_unit_code, i.lot_controlled, i.catch_weight, i.shelf_life_days, i.default_receipt_status,
    i.consumption_mode, i.costing_method, i.standard_cost_per_base, i.reorder_point_base, i.min_qty_base, i.max_qty_base,
    i.ttb_material_category, i.units_per_case, i.active, i.notes, i.created_at, i.updated_at';

/** Every item class row by code, in display order; cached for the request. */
function item_class_rows(PDO $pdo): array
{
    if (!isset($GLOBALS['__item_class_rows'])) {
        $rows = [];
        foreach ($pdo->query('SELECT id, code, name, kind, purchasable, recipe_ingredient, display_order, is_builtin, active, notes FROM app.item_classes ORDER BY display_order, name') as $row) {
            $rows[$row['code']] = $row;
        }
        $GLOBALS['__item_class_rows'] = $rows;
    }
    return $GLOBALS['__item_class_rows'];
}

/** Forget the cached rows after a class is saved. */
function item_class_cache_reset(): void
{
    unset($GLOBALS['__item_class_rows']);
}

/**
 * Item classes as code => name for selects and filters. $kind limits to 'material' or 'finished';
 * inactive classes are left out unless $activeOnly is false or the code is in $keepCodes (an
 * existing record's class stays selectable).
 */
function item_class_options(PDO $pdo, ?string $kind = null, bool $activeOnly = true, array $keepCodes = []): array
{
    $options = [];
    foreach (item_class_rows($pdo) as $code => $row) {
        if ($kind !== null && $row['kind'] !== $kind) { continue; }
        if ($activeOnly && !$row['active'] && !in_array($code, $keepCodes, true)) { continue; }
        $options[$code] = $row['name'];
    }
    return $options;
}

/** The display name of a class, or the code when it is unknown. */
function item_class_name(PDO $pdo, ?string $code): string
{
    return item_class_rows($pdo)[$code]['name'] ?? (string) $code;
}

/** Active class codes with a behaviour flag set: 'purchasable' or 'recipe_ingredient'. */
function item_classes_where(PDO $pdo, string $flag): array
{
    $codes = [];
    foreach (item_class_rows($pdo) as $code => $row) {
        if ($row['active'] && !empty($row[$flag])) { $codes[] = $code; }
    }
    return $codes;
}

/** Default receipt status for a class: quarantine for fruit, juice, yeast, additive; released otherwise. */
function items_default_receipt_status(string $itemClass): string
{
    return in_array($itemClass, ITEM_QUARANTINE_CLASSES, true) ? 'quarantine' : 'released';
}

/** Base-unit options (app.units where is_base) as code => "code (name)". */
function items_base_unit_options(): array
{
    $options = [];
    foreach (unit_table() as $code => $unit) {
        if ($unit['is_base']) {
            $options[$code] = $code . ' (' . $unit['name'] . ')';
        }
    }
    return $options;
}

/** Fruit weights use the fruit display unit. */
function items_unit_kind(?string $itemClass): string
{
    return $itemClass === 'fruit' ? 'fruit' : 'default';
}

/** A number without trailing zeros, for form inputs and factor text. */
function items_plain_number(float|string|null $value, int $decimals = 8): string
{
    if ($value === null || $value === '') {
        return '';
    }
    $text = number_format((float) $value, $decimals, '.', '');
    return str_contains($text, '.') ? rtrim(rtrim($text, '0'), '.') : $text;
}

/** An items row converted to the values the form shows (quantities and cost in display units). */
function items_form_values(array $item): array
{
    $base = (string) $item['base_unit_code'];
    $kind = items_unit_kind($item['item_class']);
    $item['reorder_point'] = items_plain_number(to_display($item['reorder_point_base'], $base, $kind), 3);
    $item['min_qty'] = items_plain_number(to_display($item['min_qty_base'], $base, $kind), 3);
    $item['max_qty'] = items_plain_number(to_display($item['max_qty_base'], $base, $kind), 3);
    $item['standard_cost_per_base'] = $item['standard_cost_per_base'] === null ? ''
        : items_plain_number((float) $item['standard_cost_per_base'] * unit_factor(display_unit($base, $kind)), 3);
    return $item;
}

function find_items(PDO $pdo, string $search = '', string $sort = 'code', int $page = 1, ?string $itemClass = null, bool $activeOnly = true): array
{
    $clauses = [];
    $params = [];
    if ($search !== '') {
        $clauses[] = '(i.code ILIKE :s OR i.name ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    if ($itemClass !== null && $itemClass !== '') {
        $clauses[] = 'i.item_class = :item_class';
        $params['item_class'] = $itemClass;
    }
    if ($activeOnly) {
        $clauses[] = 'i.active';
    }
    $where = $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);
    return paged_query(
        $pdo,
        'SELECT ' . ITEM_COLUMNS . ' FROM app.items i' . $where . ' ORDER BY ' . order_by($sort, ITEM_SORTS, 'code'),
        'SELECT count(*) FROM app.items i' . $where,
        $params,
        $page
    );
}

function find_item(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT ' . ITEM_COLUMNS . ' FROM app.items i WHERE i.id = :id');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function find_item_by_code(PDO $pdo, string $code): ?array
{
    $statement = $pdo->prepare('SELECT ' . ITEM_COLUMNS . ' FROM app.items i WHERE lower(i.code) = lower(:code)');
    $statement->execute(['code' => $code]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** Active items as id => "CODE — Name", optionally limited to a list of item classes. */
function item_options(PDO $pdo, ?array $classes = null): array
{
    $sql = 'SELECT id, code || \' — \' || name AS label FROM app.items WHERE active';
    $params = [];
    if ($classes !== null) {
        if ($classes === []) {
            return [];
        }
        $marks = [];
        foreach (array_values($classes) as $i => $class) {
            $marks[] = ':c' . $i;
            $params['c' . $i] = $class;
        }
        $sql .= ' AND item_class IN (' . implode(', ', $marks) . ')';
    }
    $statement = $pdo->prepare($sql . ' ORDER BY code');
    $statement->execute($params);
    return array_column($statement->fetchAll(), 'label', 'id');
}

function items_write_params(string $code, string $name, string $itemClass, string $baseUnitCode, bool $lotControlled, bool $catchWeight, ?int $shelfLifeDays,
    string $defaultReceiptStatus, string $consumptionMode, string $costingMethod, ?float $standardCostPerBase, ?float $reorderPointBase,
    ?float $minQtyBase, ?float $maxQtyBase, string $ttbMaterialCategory, ?int $unitsPerCase, ?string $notes, bool $active): array
{
    $num = static fn(?float $v, int $scale): ?string => $v === null ? null : number_format($v, $scale, '.', '');
    return [
        'code' => $code, 'name' => $name, 'item_class' => $itemClass, 'base_unit_code' => $baseUnitCode,
        'lot_controlled' => $lotControlled ? 't' : 'f', 'catch_weight' => $catchWeight ? 't' : 'f', 'shelf_life_days' => $shelfLifeDays,
        'default_receipt_status' => $defaultReceiptStatus, 'consumption_mode' => $consumptionMode, 'costing_method' => $costingMethod,
        'standard_cost_per_base' => $num($standardCostPerBase, 6), 'reorder_point_base' => $num($reorderPointBase, 4),
        'min_qty_base' => $num($minQtyBase, 4), 'max_qty_base' => $num($maxQtyBase, 4),
        'ttb_material_category' => $ttbMaterialCategory, 'units_per_case' => $unitsPerCase, 'notes' => $notes, 'active' => $active ? 't' : 'f',
    ];
}

function insert_item(PDO $pdo, string $code, string $name, string $itemClass, string $baseUnitCode, bool $lotControlled, bool $catchWeight, ?int $shelfLifeDays,
    string $defaultReceiptStatus, string $consumptionMode, string $costingMethod, ?float $standardCostPerBase, ?float $reorderPointBase,
    ?float $minQtyBase, ?float $maxQtyBase, string $ttbMaterialCategory, ?int $unitsPerCase, ?string $notes, bool $active): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.items (code, name, item_class, base_unit_code, lot_controlled, catch_weight, shelf_life_days, default_receipt_status, consumption_mode,
            costing_method, standard_cost_per_base, reorder_point_base, min_qty_base, max_qty_base, ttb_material_category, units_per_case, notes, active)
        VALUES (:code, :name, :item_class, :base_unit_code, :lot_controlled, :catch_weight, :shelf_life_days, :default_receipt_status, :consumption_mode,
            :costing_method, :standard_cost_per_base, :reorder_point_base, :min_qty_base, :max_qty_base, :ttb_material_category, :units_per_case, :notes, :active)
        RETURNING id
    SQL);
    $statement->execute(items_write_params(...array_slice(func_get_args(), 1)));
    return find_item($pdo, (int) $statement->fetchColumn());
}

function update_item(PDO $pdo, int $id, string $code, string $name, string $itemClass, string $baseUnitCode, bool $lotControlled, bool $catchWeight, ?int $shelfLifeDays,
    string $defaultReceiptStatus, string $consumptionMode, string $costingMethod, ?float $standardCostPerBase, ?float $reorderPointBase,
    ?float $minQtyBase, ?float $maxQtyBase, string $ttbMaterialCategory, ?int $unitsPerCase, ?string $notes, bool $active): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.items SET code = :code, name = :name, item_class = :item_class, base_unit_code = :base_unit_code, lot_controlled = :lot_controlled,
            catch_weight = :catch_weight, shelf_life_days = :shelf_life_days, default_receipt_status = :default_receipt_status,
            consumption_mode = :consumption_mode, costing_method = :costing_method, standard_cost_per_base = :standard_cost_per_base,
            reorder_point_base = :reorder_point_base, min_qty_base = :min_qty_base, max_qty_base = :max_qty_base,
            ttb_material_category = :ttb_material_category, units_per_case = :units_per_case, notes = :notes, active = :active
        WHERE id = :id
        RETURNING id
    SQL);
    $args = func_get_args();
    $statement->execute(['id' => $id] + items_write_params(...array_slice($args, 2)));
    if ($statement->fetchColumn() === false) {
        throw new RuntimeException('Item not found.');
    }
    return find_item($pdo, $id);
}

function find_item_units(PDO $pdo, int $itemId): array
{
    $statement = $pdo->prepare('SELECT id, item_id, unit_code, unit_name, to_base_factor, is_purchase_default FROM app.item_units WHERE item_id = :item_id ORDER BY unit_code');
    $statement->execute(['item_id' => $itemId]);
    return $statement->fetchAll();
}

function find_item_unit(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT id, item_id, unit_code, unit_name, to_base_factor, is_purchase_default FROM app.item_units WHERE id = :id');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function insert_item_unit(PDO $pdo, int $itemId, string $unitCode, string $unitName, float $toBaseFactor, bool $isPurchaseDefault): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.item_units (item_id, unit_code, unit_name, to_base_factor, is_purchase_default)
        VALUES (:item_id, :unit_code, :unit_name, :to_base_factor, :is_purchase_default)
        RETURNING id, item_id, unit_code, unit_name, to_base_factor, is_purchase_default
    SQL);
    $statement->execute([
        'item_id' => $itemId, 'unit_code' => $unitCode, 'unit_name' => $unitName,
        'to_base_factor' => number_format($toBaseFactor, 8, '.', ''), 'is_purchase_default' => $isPurchaseDefault ? 't' : 'f',
    ]);
    return $statement->fetch();
}

function delete_item_unit(PDO $pdo, int $id): bool
{
    $statement = $pdo->prepare('DELETE FROM app.item_units WHERE id = :id');
    $statement->execute(['id' => $id]);
    return $statement->rowCount() > 0;
}

/** Suppliers that list the item (supplier_items joined to suppliers). */
function find_item_suppliers(PDO $pdo, int $itemId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT si.id, si.supplier_id, s.name AS supplier_name, s.kind AS supplier_kind, s.active AS supplier_active, si.supplier_sku,
               si.purchase_unit_code, si.to_base_factor, si.last_price, si.lead_time_days, si.active
        FROM app.supplier_items si JOIN app.suppliers s ON s.id = si.supplier_id
        WHERE si.item_id = :item_id ORDER BY s.name
    SQL);
    $statement->execute(['item_id' => $itemId]);
    return $statement->fetchAll();
}
