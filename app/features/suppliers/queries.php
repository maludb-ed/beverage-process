<?php
declare(strict_types=1);

const SUPPLIER_KINDS = ['vendor' => 'Vendor', 'orchard' => 'Orchard', 'juice_supplier' => 'Juice supplier', 'packaging' => 'Packaging', 'other' => 'Other'];
const SUPPLIER_SORTS = ['name' => 's.name', 'kind' => 's.kind'];
const SUPPLIER_COLUMNS = 's.id, s.name, s.kind, s.contact_name, s.email, s.phone, s.address, s.notes, s.active, s.created_at, s.updated_at';

function find_suppliers(PDO $pdo, string $search = '', string $sort = 'name', int $page = 1): array
{
    $where = '';
    $params = [];
    if ($search !== '') {
        $where = ' WHERE s.name ILIKE :s OR s.contact_name ILIKE :s OR s.email ILIKE :s';
        $params['s'] = '%' . $search . '%';
    }
    return paged_query(
        $pdo,
        'SELECT ' . SUPPLIER_COLUMNS . ' FROM app.suppliers s' . $where . ' ORDER BY ' . order_by($sort, SUPPLIER_SORTS, 'name'),
        'SELECT count(*) FROM app.suppliers s' . $where,
        $params,
        $page
    );
}

function find_supplier(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT ' . SUPPLIER_COLUMNS . ' FROM app.suppliers s WHERE s.id = :id');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** Active suppliers as id => name, for selects across the application. */
function supplier_options(PDO $pdo): array
{
    return array_column($pdo->query('SELECT id, name FROM app.suppliers WHERE active ORDER BY name')->fetchAll(), 'name', 'id');
}

function insert_supplier(PDO $pdo, string $name, string $kind, ?string $contactName, ?string $email, ?string $phone, ?string $address, ?string $notes, bool $active): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.suppliers (name, kind, contact_name, email, phone, address, notes, active)
        VALUES (:name, :kind, :contact_name, :email, :phone, :address, :notes, :active)
        RETURNING id, name, kind, contact_name, email, phone, address, notes, active
    SQL);
    $statement->execute(['name' => $name, 'kind' => $kind, 'contact_name' => $contactName, 'email' => $email, 'phone' => $phone,
        'address' => $address, 'notes' => $notes, 'active' => $active ? 't' : 'f']);
    return $statement->fetch();
}

function update_supplier(PDO $pdo, int $id, string $name, string $kind, ?string $contactName, ?string $email, ?string $phone, ?string $address, ?string $notes, bool $active): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.suppliers SET name = :name, kind = :kind, contact_name = :contact_name, email = :email, phone = :phone,
            address = :address, notes = :notes, active = :active
        WHERE id = :id
        RETURNING id, name, kind, contact_name, email, phone, address, notes, active
    SQL);
    $statement->execute(['id' => $id, 'name' => $name, 'kind' => $kind, 'contact_name' => $contactName, 'email' => $email, 'phone' => $phone,
        'address' => $address, 'notes' => $notes, 'active' => $active ? 't' : 'f']);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Supplier not found.');
    }
    return $row;
}

function find_supplier_items(PDO $pdo, int $supplierId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT si.id, si.supplier_id, si.item_id, i.code AS item_code, i.name AS item_name, i.base_unit_code, si.supplier_sku,
               si.purchase_unit_code, si.to_base_factor, si.last_price, si.lead_time_days, si.active
        FROM app.supplier_items si JOIN app.items i ON i.id = si.item_id
        WHERE si.supplier_id = :supplier_id ORDER BY i.code
    SQL);
    $statement->execute(['supplier_id' => $supplierId]);
    return $statement->fetchAll();
}

function find_supplier_item(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT id, supplier_id, item_id, supplier_sku, purchase_unit_code, to_base_factor, last_price, lead_time_days, active FROM app.supplier_items WHERE id = :id');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** Purchase-unit choices: every app.units code plus the item-specific unit codes, as code => label. */
function supplier_purchase_unit_options(PDO $pdo): array
{
    $options = [];
    foreach ($pdo->query('SELECT code, name FROM app.units ORDER BY display_order')->fetchAll() as $row) {
        $options[$row['code']] = $row['code'] . ' (' . $row['name'] . ')';
    }
    foreach ($pdo->query('SELECT DISTINCT unit_code, min(unit_name) AS unit_name FROM app.item_units GROUP BY unit_code ORDER BY unit_code')->fetchAll() as $row) {
        $options[$row['unit_code']] ??= $row['unit_code'] . ' (' . $row['unit_name'] . ', item unit)';
    }
    return $options;
}

/** The to_base_factor for a purchase unit on an item: the item's own unit first, then app.units; null when unknown. */
function supplier_unit_factor(PDO $pdo, int $itemId, string $unitCode): ?float
{
    $statement = $pdo->prepare('SELECT to_base_factor FROM app.item_units WHERE item_id = :item_id AND unit_code = :code');
    $statement->execute(['item_id' => $itemId, 'code' => $unitCode]);
    $factor = $statement->fetchColumn();
    if ($factor === false) {
        $statement = $pdo->prepare('SELECT to_base_factor FROM app.units WHERE code = :code');
        $statement->execute(['code' => $unitCode]);
        $factor = $statement->fetchColumn();
    }
    return $factor === false ? null : (float) $factor;
}

function insert_supplier_item(PDO $pdo, int $supplierId, int $itemId, ?string $supplierSku, string $purchaseUnitCode, float $toBaseFactor, ?float $lastPrice, ?int $leadTimeDays): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.supplier_items (supplier_id, item_id, supplier_sku, purchase_unit_code, to_base_factor, last_price, lead_time_days)
        VALUES (:supplier_id, :item_id, :supplier_sku, :purchase_unit_code, :to_base_factor, :last_price, :lead_time_days)
        RETURNING id, supplier_id, item_id, supplier_sku, purchase_unit_code, to_base_factor, last_price, lead_time_days, active
    SQL);
    $statement->execute([
        'supplier_id' => $supplierId, 'item_id' => $itemId, 'supplier_sku' => $supplierSku, 'purchase_unit_code' => $purchaseUnitCode,
        'to_base_factor' => number_format($toBaseFactor, 8, '.', ''), 'last_price' => $lastPrice === null ? null : number_format($lastPrice, 4, '.', ''),
        'lead_time_days' => $leadTimeDays,
    ]);
    return $statement->fetch();
}

function update_supplier_item(PDO $pdo, int $id, ?string $supplierSku, string $purchaseUnitCode, float $toBaseFactor, ?float $lastPrice, ?int $leadTimeDays): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.supplier_items SET supplier_sku = :supplier_sku, purchase_unit_code = :purchase_unit_code, to_base_factor = :to_base_factor,
            last_price = :last_price, lead_time_days = :lead_time_days
        WHERE id = :id
        RETURNING id, supplier_id, item_id, supplier_sku, purchase_unit_code, to_base_factor, last_price, lead_time_days, active
    SQL);
    $statement->execute([
        'id' => $id, 'supplier_sku' => $supplierSku, 'purchase_unit_code' => $purchaseUnitCode,
        'to_base_factor' => number_format($toBaseFactor, 8, '.', ''), 'last_price' => $lastPrice === null ? null : number_format($lastPrice, 4, '.', ''),
        'lead_time_days' => $leadTimeDays,
    ]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Supplier item not found.');
    }
    return $row;
}

/** What refers to a supplier: label => count, only the non-zero ones (its item terms are setup, not history). */
function supplier_history(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT (SELECT count(*) FROM app.purchase_orders WHERE supplier_id = :id) AS purchase_orders,
               (SELECT count(*) FROM app.goods_receipts WHERE supplier_id = :id) AS receipts,
               (SELECT count(*) FROM app.lots WHERE supplier_id = :id) AS lots
    SQL);
    $statement->execute(['id' => $id]);
    $labels = ['purchase_orders' => 'purchase orders', 'receipts' => 'receipts', 'lots' => 'lots'];
    $out = [];
    foreach ($statement->fetch() as $key => $count) {
        if ((int) $count > 0) {
            $out[$labels[$key]] = (int) $count;
        }
    }
    return $out;
}

/** Delete a supplier with no history (its item terms go with it); one with history is deactivated instead (returns false). */
function delete_supplier(PDO $pdo, int $id): bool
{
    if (supplier_history($pdo, $id) !== []) {
        $pdo->prepare('UPDATE app.suppliers SET active = false WHERE id = :id')->execute(['id' => $id]);
        return false;
    }
    $pdo->prepare('DELETE FROM app.suppliers WHERE id = :id')->execute(['id' => $id]);
    return true;
}

