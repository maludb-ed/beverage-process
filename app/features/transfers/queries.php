<?php
declare(strict_types=1);

require_once __DIR__ . '/../inventory/queries.php';

const TRANSFER_STATUSES = ['draft' => 'Draft', 'posted' => 'Posted', 'cancelled' => 'Cancelled'];
const TRANSFER_SORTS = ['number' => 't.number', 'transferred_at' => 't.transferred_at', 'status' => 't.status'];
const TRANSFER_TAX_STATE_MESSAGE = 'Record a removal or return to move between bonded and tax-paid locations';

function find_transfers(PDO $pdo, string $search = '', ?string $status = null, string $sort = '-transferred_at', int $page = 1): array
{
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(t.number ILIKE :s OR fl.name ILIKE :s OR tl.name ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    if ($status) { $where[] = 't.status = :status'; $params['status'] = $status; }
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    $from = ' FROM app.inventory_transfers t JOIN app.locations fl ON fl.id = t.from_location_id JOIN app.locations tl ON tl.id = t.to_location_id LEFT JOIN app.users u ON u.id = t.posted_by';
    return paged_query($pdo,
        'SELECT t.id, t.number, t.status, t.transferred_at, fl.name AS from_name, tl.name AS to_name, u.display_name AS posted_by_name,
                (SELECT count(*) FROM app.inventory_transfer_lines l WHERE l.transfer_id = t.id) AS line_count'
            . $from . $whereSql . ' ORDER BY ' . order_by($sort, TRANSFER_SORTS, '-transferred_at') . ', t.id DESC',
        'SELECT count(*)' . $from . $whereSql, $params, $page);
}

/** Header plus lines (with item, lot and cost facts) in $row['lines']. */
function find_transfer(PDO $pdo, int $id, bool $forUpdate = false): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT t.*, fl.name AS from_name, fl.tax_state AS from_tax_state, fl.premises_id AS from_premises_id,
               tl.name AS to_name, tl.tax_state AS to_tax_state, tl.premises_id AS to_premises_id,
               uc.display_name AS created_by_name, up.display_name AS posted_by_name
        FROM app.inventory_transfers t
        JOIN app.locations fl ON fl.id = t.from_location_id
        JOIN app.locations tl ON tl.id = t.to_location_id
        LEFT JOIN app.users uc ON uc.id = t.created_by
        LEFT JOIN app.users up ON up.id = t.posted_by
        WHERE t.id = :id
    SQL . ($forUpdate ? ' FOR UPDATE OF t' : ''));
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    if ($row === false) {
        return null;
    }
    $lines = $pdo->prepare(<<<'SQL'
        SELECT l.id, l.item_id, l.lot_id, l.qty_base, i.code AS item_code, i.name AS item_name, i.item_class, i.base_unit_code,
               lot.lot_number, lot.quality_status, lot.expires_on, lot.unit_cost_base
        FROM app.inventory_transfer_lines l
        JOIN app.items i ON i.id = l.item_id
        JOIN app.lots lot ON lot.id = l.lot_id
        WHERE l.transfer_id = :id ORDER BY l.id
    SQL);
    $lines->execute(['id' => $id]);
    $row['lines'] = $lines->fetchAll();
    return $row;
}

function replace_transfer_lines(PDO $pdo, int $transferId, array $lines): void
{
    $pdo->prepare('DELETE FROM app.inventory_transfer_lines WHERE transfer_id = :id')->execute(['id' => $transferId]);
    $insert = $pdo->prepare('INSERT INTO app.inventory_transfer_lines (transfer_id, item_id, lot_id, qty_base) VALUES (:t, :item, :lot, :qty)');
    foreach ($lines as $line) {
        $insert->execute(['t' => $transferId, 'item' => $line['item_id'], 'lot' => $line['lot_id'], 'qty' => $line['qty_base']]);
    }
}

/** Each line: item_id, lot_id, qty_base. Number from app.next_number('transfer'). */
function insert_transfer(PDO $pdo, int $fromLocationId, int $toLocationId, string $transferredAt, ?string $notes, int $createdBy, array $lines): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.inventory_transfers (number, from_location_id, to_location_id, transferred_at, notes, created_by)
        VALUES (app.next_number('transfer'), :f, :t, :at, :notes, :by)
        RETURNING id, number, status, from_location_id, to_location_id, transferred_at
    SQL);
    $statement->execute(['f' => $fromLocationId, 't' => $toLocationId, 'at' => $transferredAt, 'notes' => $notes, 'by' => $createdBy]);
    $row = $statement->fetch();
    replace_transfer_lines($pdo, (int) $row['id'], $lines);
    return $row;
}

function update_transfer(PDO $pdo, int $id, int $fromLocationId, int $toLocationId, string $transferredAt, ?string $notes, array $lines): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.inventory_transfers SET from_location_id = :f, to_location_id = :t, transferred_at = :at, notes = :notes
        WHERE id = :id AND status = 'draft'
        RETURNING id, number, status, from_location_id, to_location_id, transferred_at
    SQL);
    $statement->execute(['id' => $id, 'f' => $fromLocationId, 't' => $toLocationId, 'at' => $transferredAt, 'notes' => $notes]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Only draft transfers can be edited.');
    }
    replace_transfer_lines($pdo, $id, $lines);
    return $row;
}

/**
 * Post a draft transfer: two ledger rows (transfer_out, transfer_in) per line sharing one group id.
 * Caller owns the transaction and the activity log row. Returns the number, group ids and rows.
 */
function post_transfer(PDO $pdo, int $id, int $actorId): array
{
    $transfer = find_transfer($pdo, $id, true) ?? throw new RuntimeException('That transfer does not exist.');
    if ($transfer['status'] !== 'draft') {
        throw new RuntimeException('Transfer ' . $transfer['number'] . ' is already ' . $transfer['status'] . '.');
    }
    if ($transfer['lines'] === []) {
        throw new RuntimeException('Transfer ' . $transfer['number'] . ' has no lines to post.');
    }
    if ($transfer['from_tax_state'] !== $transfer['to_tax_state']) {
        throw new RuntimeException(TRANSFER_TAX_STATE_MESSAGE . '.');
    }
    $from = (int) $transfer['from_location_id'];
    $to = (int) $transfer['to_location_id'];
    $groups = [];
    $rows = [];
    foreach ($transfer['lines'] as $line) {
        $qty = (float) $line['qty_base'];
        $balance = find_balance($pdo, (int) $line['item_id'], (int) $line['lot_id'], $from);
        if ($balance === null || round((float) $balance['qty_available'], 4) < round($qty, 4)) {
            throw new RuntimeException('Not enough of lot ' . $line['lot_number'] . ' at ' . $transfer['from_name'] . ': '
                . fmt_qty($balance['qty_available'] ?? 0, $line['base_unit_code'], 1, inventory_unit_kind($line['item_class'])) . ' available.');
        }
        $group = new_group_id();
        $groups[] = $group;
        $cost = (float) $line['unit_cost_base'];
        $key = 'transfer:' . $id . ':' . $line['id'] . ':';
        $out = insert_inventory_transaction($pdo, $group, 'transfer_out', (int) $line['item_id'], (int) $line['lot_id'], $from, (int) $transfer['from_premises_id'],
            -$qty, $cost, 'location', $to, null, 'none', 'transfer', $id, $key . 'out', (string) $transfer['transferred_at'], $actorId);
        $in = insert_inventory_transaction($pdo, $group, 'transfer_in', (int) $line['item_id'], (int) $line['lot_id'], $to, (int) $transfer['to_premises_id'],
            $qty, $cost, 'location', $from, null, 'none', 'transfer', $id, $key . 'in', (string) $transfer['transferred_at'], $actorId);
        $rows[] = ['lot' => $line['lot_number'], 'qty_base' => $qty, 'group_id' => $group, 'out' => $out['id'], 'in' => $in['id']];
    }
    $statement = $pdo->prepare("UPDATE app.inventory_transfers SET status = 'posted', posted_by = :by, posted_at = now() WHERE id = :id AND status = 'draft'");
    $statement->execute(['id' => $id, 'by' => $actorId]);
    return ['number' => $transfer['number'], 'groups' => $groups, 'lines' => $rows];
}

function cancel_transfer(PDO $pdo, int $id, int $actorId): bool
{
    $statement = $pdo->prepare("UPDATE app.inventory_transfers SET status = 'cancelled' WHERE id = :id AND status = 'draft'");
    $statement->execute(['id' => $id]);
    return $statement->rowCount() === 1;
}

/** Released lots with available stock at a location for one item, FEFO order. */
function find_lots_at_location(PDO $pdo, int $itemId, int $locationId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT lot_id, lot_number, expires_on, received_on, qty_on_hand, qty_available, base_unit_code, item_class
        FROM app.v_lot_balances
        WHERE item_id = :item AND location_id = :loc AND quality_status = 'released' AND qty_available > 0
        ORDER BY expires_on NULLS LAST, received_on, lot_id
    SQL);
    $statement->execute(['item' => $itemId, 'loc' => $locationId]);
    return $statement->fetchAll();
}

/** Attach the lot choices and item facts each form line needs to render. */
function transfer_prepare_lines(PDO $pdo, ?int $fromLocationId, array $lines): array
{
    $facts = inventory_item_facts($pdo, array_map(static fn($l) => (int) ($l['item_id'] ?? 0), $lines));
    foreach ($lines as $n => $line) {
        $itemId = (int) ($line['item_id'] ?? 0);
        $lines[$n]['item_facts'] = $facts[$itemId] ?? null;
        $lines[$n]['lots'] = $itemId && $fromLocationId ? find_lots_at_location($pdo, $itemId, $fromLocationId) : [];
    }
    return $lines;
}
