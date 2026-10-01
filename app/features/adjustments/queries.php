<?php
declare(strict_types=1);

require_once __DIR__ . '/../inventory/queries.php';
require_once __DIR__ . '/../items/queries.php';

const ADJUSTMENT_STATUSES = ['draft' => 'Draft', 'pending_approval' => 'Pending approval', 'posted' => 'Posted', 'cancelled' => 'Cancelled'];
const ADJUSTMENT_SORTS = ['number' => 'a.number', 'adjusted_at' => 'a.adjusted_at', 'status' => 'a.status'];
/** Ledger ttb_category values an adjustment reason code may carry through. */
const ADJUSTMENT_LEDGER_TTB = ['destroyed', 'breakage', 'inventory_loss', 'casualty_loss', 'shortage', 'testing'];

function find_adjustments(PDO $pdo, string $search = '', ?string $status = null, string $sort = '-adjusted_at', int $page = 1, ?int $reasonCodeId = null): array
{
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(a.number ILIKE :s OR loc.name ILIKE :s OR rc.code ILIKE :s OR rc.name ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    if ($status) { $where[] = 'a.status = :status'; $params['status'] = $status; }
    if ($reasonCodeId !== null) { $where[] = 'a.reason_code_id = :rc'; $params['rc'] = $reasonCodeId; }
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    $from = ' FROM app.inventory_adjustments a JOIN app.locations loc ON loc.id = a.location_id JOIN app.reason_codes rc ON rc.id = a.reason_code_id LEFT JOIN app.users u ON u.id = a.created_by';
    return paged_query($pdo,
        'SELECT a.id, a.number, a.status, a.adjusted_at, loc.name AS location_name, rc.code AS reason_code, rc.name AS reason_name, u.display_name AS created_by_name,
                (SELECT count(*) FROM app.inventory_adjustment_lines l WHERE l.adjustment_id = a.id) AS line_count,
                (SELECT sum(l.qty_delta_base) FROM app.inventory_adjustment_lines l WHERE l.adjustment_id = a.id) AS net_qty,
                (SELECT count(DISTINCT l.item_id) FROM app.inventory_adjustment_lines l WHERE l.adjustment_id = a.id) AS item_count,
                (SELECT i.base_unit_code FROM app.inventory_adjustment_lines l JOIN app.items i ON i.id = l.item_id WHERE l.adjustment_id = a.id ORDER BY l.id LIMIT 1) AS base_unit_code,
                (SELECT i.item_class FROM app.inventory_adjustment_lines l JOIN app.items i ON i.id = l.item_id WHERE l.adjustment_id = a.id ORDER BY l.id LIMIT 1) AS item_class'
            . $from . $whereSql . ' ORDER BY ' . order_by($sort, ADJUSTMENT_SORTS, '-adjusted_at') . ', a.id DESC',
        'SELECT count(*)' . $from . $whereSql, $params, $page);
}

/** Header (location, reason and approval threshold) plus lines in $row['lines']. */
function find_adjustment(PDO $pdo, int $id, bool $forUpdate = false): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT a.*, loc.name AS location_name, loc.tax_state AS location_tax_state, loc.premises_id, loc.allow_negative,
               rc.code AS reason_code, rc.name AS reason_name, rc.ttb_category AS reason_ttb_category, rc.requires_approval_above,
               uc.display_name AS created_by_name, ua.display_name AS approved_by_name, up.display_name AS posted_by_name
        FROM app.inventory_adjustments a
        JOIN app.locations loc ON loc.id = a.location_id
        JOIN app.reason_codes rc ON rc.id = a.reason_code_id
        LEFT JOIN app.users uc ON uc.id = a.created_by
        LEFT JOIN app.users ua ON ua.id = a.approved_by
        LEFT JOIN app.users up ON up.id = a.posted_by
        WHERE a.id = :id
    SQL . ($forUpdate ? ' FOR UPDATE OF a' : ''));
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    if ($row === false) {
        return null;
    }
    $lines = $pdo->prepare(<<<'SQL'
        SELECT l.id, l.item_id, l.lot_id, l.qty_delta_base, l.unit_cost_base, l.note, i.code AS item_code, i.name AS item_name, i.item_class, i.base_unit_code,
               lot.lot_number, lot.quality_status, lot.unit_cost_base AS lot_unit_cost_base
        FROM app.inventory_adjustment_lines l
        JOIN app.items i ON i.id = l.item_id
        JOIN app.lots lot ON lot.id = l.lot_id
        WHERE l.adjustment_id = :id ORDER BY l.id
    SQL);
    $lines->execute(['id' => $id]);
    $row['lines'] = $lines->fetchAll();
    return $row;
}

/** True when the reason code has a threshold and the summed absolute base quantity is above it. */
function adjustment_needs_approval(?float $threshold, array $lines): bool
{
    if ($threshold === null) {
        return false;
    }
    $total = 0.0;
    foreach ($lines as $line) {
        $total += abs((float) ($line['qty_delta_base'] ?? 0));
    }
    return $total > $threshold;
}

function adjustment_reason_threshold(PDO $pdo, int $reasonCodeId): ?float
{
    $statement = $pdo->prepare('SELECT requires_approval_above FROM app.reason_codes WHERE id = :id');
    $statement->execute(['id' => $reasonCodeId]);
    $value = $statement->fetchColumn();
    return $value === false || $value === null ? null : (float) $value;
}

function replace_adjustment_lines(PDO $pdo, int $adjustmentId, array $lines): void
{
    $pdo->prepare('DELETE FROM app.inventory_adjustment_lines WHERE adjustment_id = :id')->execute(['id' => $adjustmentId]);
    $insert = $pdo->prepare('INSERT INTO app.inventory_adjustment_lines (adjustment_id, item_id, lot_id, qty_delta_base, unit_cost_base, note) VALUES (:a, :item, :lot, :qty, :cost, :note)');
    foreach ($lines as $line) {
        $insert->execute(['a' => $adjustmentId, 'item' => $line['item_id'], 'lot' => $line['lot_id'], 'qty' => $line['qty_delta_base'], 'cost' => $line['unit_cost_base'], 'note' => $line['note']]);
    }
}

/** Each line: item_id, lot_id, qty_delta_base, unit_cost_base (nullable), note (nullable). Status is draft or pending_approval. */
function insert_adjustment(PDO $pdo, int $locationId, int $reasonCodeId, string $adjustedAt, ?string $notes, int $createdBy, array $lines): array
{
    $status = adjustment_needs_approval(adjustment_reason_threshold($pdo, $reasonCodeId), $lines) ? 'pending_approval' : 'draft';
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.inventory_adjustments (number, location_id, reason_code_id, status, adjusted_at, notes, created_by)
        VALUES (app.next_number('adjustment'), :loc, :rc, :status, :at, :notes, :by)
        RETURNING id, number, status, location_id, reason_code_id, adjusted_at
    SQL);
    $statement->execute(['loc' => $locationId, 'rc' => $reasonCodeId, 'status' => $status, 'at' => $adjustedAt, 'notes' => $notes, 'by' => $createdBy]);
    $row = $statement->fetch();
    replace_adjustment_lines($pdo, (int) $row['id'], $lines);
    return $row;
}

/** Draft or pending only. Any earlier approval is cleared and the status is recomputed from the new lines. */
function update_adjustment(PDO $pdo, int $id, int $locationId, int $reasonCodeId, string $adjustedAt, ?string $notes, array $lines): array
{
    $status = adjustment_needs_approval(adjustment_reason_threshold($pdo, $reasonCodeId), $lines) ? 'pending_approval' : 'draft';
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.inventory_adjustments SET location_id = :loc, reason_code_id = :rc, status = :status, adjusted_at = :at, notes = :notes, approved_by = NULL, approved_at = NULL
        WHERE id = :id AND status IN ('draft', 'pending_approval')
        RETURNING id, number, status, location_id, reason_code_id, adjusted_at
    SQL);
    $statement->execute(['id' => $id, 'loc' => $locationId, 'rc' => $reasonCodeId, 'status' => $status, 'at' => $adjustedAt, 'notes' => $notes]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Only draft adjustments can be edited.');
    }
    replace_adjustment_lines($pdo, $id, $lines);
    return $row;
}

/** Owner approval: pending_approval becomes draft with approved_by and approved_at set. */
function approve_adjustment(PDO $pdo, int $id, int $actorId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.inventory_adjustments SET status = 'draft', approved_by = :by, approved_at = now()
        WHERE id = :id AND status = 'pending_approval'
        RETURNING id, number, status, approved_by, approved_at
    SQL);
    $statement->execute(['id' => $id, 'by' => $actorId]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Only an adjustment pending approval can be approved.');
    }
    return $row;
}

/**
 * Post a draft adjustment: one ledger row per line (txn_type adjustment). Caller owns the transaction and the log row.
 * Refuses a draft whose reason threshold applies but has no approval.
 */
function post_adjustment(PDO $pdo, int $id, int $actorId): array
{
    $adjustment = find_adjustment($pdo, $id, true) ?? throw new RuntimeException('That adjustment does not exist.');
    if ($adjustment['status'] === 'pending_approval') {
        throw new RuntimeException('Adjustment ' . $adjustment['number'] . ' needs owner approval before it can be posted.');
    }
    if ($adjustment['status'] !== 'draft') {
        throw new RuntimeException('Adjustment ' . $adjustment['number'] . ' is already ' . $adjustment['status'] . '.');
    }
    if ($adjustment['lines'] === []) {
        throw new RuntimeException('Adjustment ' . $adjustment['number'] . ' has no lines to post.');
    }
    $threshold = $adjustment['requires_approval_above'] === null ? null : (float) $adjustment['requires_approval_above'];
    if (adjustment_needs_approval($threshold, $adjustment['lines']) && $adjustment['approved_at'] === null) {
        throw new RuntimeException('Adjustment ' . $adjustment['number'] . ' needs owner approval before it can be posted.');
    }
    $group = new_group_id();
    $rows = [];
    foreach ($adjustment['lines'] as $line) {
        $qty = (float) $line['qty_delta_base'];
        // Loss categories apply to write-downs only; a write-up is an inventory gain unless the reason carries no category.
        if ($qty < 0) {
            $ttb = in_array($adjustment['reason_ttb_category'], ADJUSTMENT_LEDGER_TTB, true) ? $adjustment['reason_ttb_category'] : 'none';
        } else {
            $ttb = $adjustment['reason_ttb_category'] !== 'none' ? 'inventory_gain' : 'none';
        }
        $cost = $line['unit_cost_base'] !== null ? (float) $line['unit_cost_base'] : (float) $line['lot_unit_cost_base'];
        $txn = insert_inventory_transaction($pdo, $group, 'adjustment', (int) $line['item_id'], (int) $line['lot_id'], (int) $adjustment['location_id'], (int) $adjustment['premises_id'],
            $qty, $cost, 'none', null, (int) $adjustment['reason_code_id'], $ttb, 'adjustment', $id, 'adjustment:' . $id . ':' . $line['id'] . ':adj',
            (string) $adjustment['adjusted_at'], $actorId, $line['note']);
        $rows[] = ['ledger_id' => $txn['id'], 'lot' => $line['lot_number'], 'qty_base' => $qty, 'ttb_category' => $ttb];
    }
    $pdo->prepare("UPDATE app.inventory_adjustments SET status = 'posted', posted_by = :by, posted_at = now() WHERE id = :id AND status = 'draft'")->execute(['id' => $id, 'by' => $actorId]);
    return ['number' => $adjustment['number'], 'group_id' => $group, 'lines' => $rows];
}

function cancel_adjustment(PDO $pdo, int $id, int $actorId): bool
{
    $statement = $pdo->prepare("UPDATE app.inventory_adjustments SET status = 'cancelled' WHERE id = :id AND status IN ('draft', 'pending_approval')");
    $statement->execute(['id' => $id]);
    return $statement->rowCount() === 1;
}

/** Lots of an item for the line picker: lots with a balance at the location first, then any other lot of the item. Any quality status. */
function find_adjustment_lots(PDO $pdo, int $itemId, int $locationId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT l.id AS lot_id, l.lot_number, l.quality_status, l.expires_on, COALESCE(b.qty_on_hand, 0) AS qty_here, i.base_unit_code, i.item_class
        FROM app.lots l
        JOIN app.items i ON i.id = l.item_id
        LEFT JOIN app.inventory_balances b ON b.lot_id = l.id AND b.location_id = :loc
        WHERE l.item_id = :item
        ORDER BY (COALESCE(b.qty_on_hand, 0) <> 0) DESC, l.expires_on NULLS LAST, l.received_on, l.id
    SQL);
    $statement->execute(['item' => $itemId, 'loc' => $locationId]);
    return $statement->fetchAll();
}

/** Attach the lot choices and item facts each form line needs to render. */
function adjustment_prepare_lines(PDO $pdo, ?int $locationId, array $lines): array
{
    $facts = inventory_item_facts($pdo, array_map(static fn($l) => (int) ($l['item_id'] ?? 0), $lines));
    foreach ($lines as $n => $line) {
        $itemId = (int) ($line['item_id'] ?? 0);
        $lines[$n]['item_facts'] = $facts[$itemId] ?? null;
        $lines[$n]['lots'] = $itemId ? find_adjustment_lots($pdo, $itemId, (int) $locationId) : [];
    }
    return $lines;
}
