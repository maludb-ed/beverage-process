<?php
declare(strict_types=1);

// Customers: removal destinations (distributors, retailers, bonded consignees).

const CUSTOMER_KINDS = [
    'distributor' => 'Distributor', 'retailer' => 'Retailer', 'taproom' => 'Taproom', 'consumer' => 'Consumer',
    'bonded_premises' => 'Bonded premises', 'other' => 'Other',
];
const CUSTOMER_DESTINATIONS = [
    'tax_paid_sale' => 'Tax-paid sale', 'taproom_transfer' => 'Taproom transfer', 'in_bond_transfer' => 'In-bond transfer', 'export' => 'Export',
];
const CUSTOMER_SORTS = ['name' => 'c.name', 'kind' => 'c.kind', 'created_at' => 'c.created_at'];
const CUSTOMER_COLUMNS = 'c.id, c.name, c.kind, c.default_destination, c.permit_number, c.contact_name, c.email, c.phone, c.address, c.notes, c.active, c.created_at, c.updated_at';

function find_customers(PDO $pdo, string $search = '', string $sort = 'name', int $page = 1): array
{
    $where = '';
    $params = [];
    if ($search !== '') {
        $where = ' WHERE (c.name ILIKE :s OR c.contact_name ILIKE :s OR c.email ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    return paged_query($pdo,
        'SELECT ' . CUSTOMER_COLUMNS . ",
                (SELECT count(*) FROM app.kegs k WHERE k.current_holder_kind = 'customer' AND k.current_holder_id = c.id) AS kegs_out,
                (SELECT max(r.removed_at) FROM app.removals r WHERE r.customer_id = c.id AND r.posted_at IS NOT NULL AND r.direction = 'out') AS last_removal_at
         FROM app.customers c" . $where . ' ORDER BY ' . order_by($sort, CUSTOMER_SORTS, 'name'),
        'SELECT count(*) FROM app.customers c' . $where, $params, $page);
}

function find_customer(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT ' . CUSTOMER_COLUMNS . ",
            (SELECT count(*) FROM app.kegs k WHERE k.current_holder_kind = 'customer' AND k.current_holder_id = c.id) AS kegs_out,
            (SELECT count(*) FROM app.removals r WHERE r.customer_id = c.id) AS removal_count
        FROM app.customers c WHERE c.id = :id");
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** Every removal and return of this customer, newest first, with units and gallons. */
function find_customer_removals(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT r.id, r.number, r.direction, r.destination_kind, r.status, r.removed_at, r.reference, r.wine_gallons, r.tax_amount, r.tax_determined,
               (SELECT COALESCE(sum(l.units), 0) FROM app.removal_lines l WHERE l.removal_id = r.id) AS units
        FROM app.removals r WHERE r.customer_id = :id
        ORDER BY r.removed_at DESC, r.id DESC
    SQL);
    $statement->execute(['id' => $id]);
    return $statement->fetchAll();
}

function find_customer_kegs_out(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT k.id, k.serial, k.size_l, k.state, k.current_lot_id, l.lot_number, k.last_moved_at,
               (now()::date - k.last_moved_at::date) AS days_out
        FROM app.kegs k LEFT JOIN app.lots l ON l.id = k.current_lot_id
        WHERE k.current_holder_kind = 'customer' AND k.current_holder_id = :id
        ORDER BY k.last_moved_at NULLS LAST, k.serial
    SQL);
    $statement->execute(['id' => $id]);
    return $statement->fetchAll();
}

/** Active customers as id => name (plus $keepId even when inactive), for the removal form. */
function customers_options(PDO $pdo, ?int $keepId = null): array
{
    $statement = $pdo->prepare('SELECT id, name FROM app.customers WHERE active OR id = :keep ORDER BY name');
    $statement->execute(['keep' => $keepId]);
    return array_column($statement->fetchAll(), 'name', 'id');
}

function insert_customer(PDO $pdo, string $name, string $kind, string $defaultDestination, ?string $permitNumber, ?string $contactName, ?string $email, ?string $phone, ?string $address, ?string $notes, bool $active): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.customers (name, kind, default_destination, permit_number, contact_name, email, phone, address, notes, active)
        VALUES (:name, :kind, :dest, :permit, :contact, :email, :phone, :address, :notes, :active)
        RETURNING id, name, kind, default_destination, permit_number, contact_name, email, phone, address, notes, active
    SQL);
    $statement->execute(['name' => $name, 'kind' => $kind, 'dest' => $defaultDestination, 'permit' => $permitNumber, 'contact' => $contactName,
        'email' => $email, 'phone' => $phone, 'address' => $address, 'notes' => $notes, 'active' => $active ? 't' : 'f']);
    return $statement->fetch();
}

function update_customer(PDO $pdo, int $id, string $name, string $kind, string $defaultDestination, ?string $permitNumber, ?string $contactName, ?string $email, ?string $phone, ?string $address, ?string $notes, bool $active): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.customers SET name = :name, kind = :kind, default_destination = :dest, permit_number = :permit, contact_name = :contact,
               email = :email, phone = :phone, address = :address, notes = :notes, active = :active
        WHERE id = :id
        RETURNING id, name, kind, default_destination, permit_number, contact_name, email, phone, address, notes, active
    SQL);
    $statement->execute(['id' => $id, 'name' => $name, 'kind' => $kind, 'dest' => $defaultDestination, 'permit' => $permitNumber, 'contact' => $contactName,
        'email' => $email, 'phone' => $phone, 'address' => $address, 'notes' => $notes, 'active' => $active ? 't' : 'f']);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('That customer does not exist.');
    }
    return $row;
}

/** Deletes when no removal or keg movement references the customer; otherwise deactivates. True when deleted. */
/** What refers to a customer: label => count, only the non-zero ones. Empty means it can be deleted outright. */
function customer_history(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT (SELECT count(*) FROM app.sales_orders WHERE customer_id = :id) AS orders,
               (SELECT count(*) FROM app.standing_orders WHERE customer_id = :id) AS standing_orders,
               (SELECT count(*) FROM app.removals WHERE customer_id = :id) AS removals,
               (SELECT count(*) FROM app.keg_movements WHERE customer_id = :id)
             + (SELECT count(*) FROM app.kegs WHERE current_holder_kind = 'customer' AND current_holder_id = :id) AS keg_records
    SQL);
    $statement->execute(['id' => $id]);
    $labels = ['orders' => 'orders', 'standing_orders' => 'standing orders', 'removals' => 'removals', 'keg_records' => 'keg records'];
    $out = [];
    foreach ($statement->fetch() as $key => $count) {
        if ((int) $count > 0) {
            $out[$labels[$key]] = (int) $count;
        }
    }
    return $out;
}

/** Delete a customer with no history; one with history is deactivated instead (returns false). */
function delete_customer(PDO $pdo, int $id): bool
{
    if (customer_history($pdo, $id) !== []) {
        $pdo->prepare('UPDATE app.customers SET active = false WHERE id = :id')->execute(['id' => $id]);
        return false;
    }
    $pdo->prepare('DELETE FROM app.customers WHERE id = :id')->execute(['id' => $id]);
    return true;
}
