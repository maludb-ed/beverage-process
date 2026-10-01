<?php
declare(strict_types=1);

require_once __DIR__ . '/../inventory/queries.php';
require_once __DIR__ . '/../items/queries.php';

const COUNT_STATUSES = ['open' => 'Open', 'counting' => 'Counting', 'review' => 'Review', 'approved' => 'Approved', 'cancelled' => 'Cancelled'];
const COUNT_KINDS = ['cycle' => 'Cycle', 'physical' => 'Physical'];
const COUNT_SORTS = ['number' => 'c.number', 'started_at' => 'c.started_at', 'status' => 'c.status'];

function find_counts(PDO $pdo, string $search = '', ?string $status = null, string $sort = '-started_at', int $page = 1, ?string $kind = null): array
{
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(c.number ILIKE :s OR loc.name ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    if ($status) { $where[] = 'c.status = :status'; $params['status'] = $status; }
    if ($kind) { $where[] = 'c.kind = :kind'; $params['kind'] = $kind; }
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    $from = ' FROM app.inventory_counts c JOIN app.locations loc ON loc.id = c.location_id LEFT JOIN app.users ua ON ua.id = c.approved_by';
    return paged_query($pdo,
        'SELECT c.id, c.number, c.kind, c.status, c.started_at, loc.name AS location_name, ua.display_name AS approved_by_name,
                (SELECT count(*) FROM app.inventory_count_lines l WHERE l.count_id = c.id) AS line_count,
                (SELECT count(*) FROM app.inventory_count_lines l WHERE l.count_id = c.id AND l.variance_base <> 0) AS variance_count'
            . $from . $whereSql . ' ORDER BY ' . order_by($sort, COUNT_SORTS, '-started_at') . ', c.id DESC',
        'SELECT count(*)' . $from . $whereSql, $params, $page);
}

const COUNT_LINE_SELECT = <<<'SQL'
    SELECT l.id, l.count_id, l.item_id, l.lot_id, l.qty_expected_base, l.qty_counted_base, l.variance_base, l.counted_by, l.counted_at, l.note,
           i.code AS item_code, i.name AS item_name, i.item_class, i.base_unit_code, lot.lot_number, lot.quality_status, lot.unit_cost_base,
           u.display_name AS counted_by_name
    FROM app.inventory_count_lines l
    JOIN app.items i ON i.id = l.item_id
    JOIN app.lots lot ON lot.id = l.lot_id
    LEFT JOIN app.users u ON u.id = l.counted_by
SQL;

/** Header plus lines in $row['lines']. */
function find_count(PDO $pdo, int $id, bool $forUpdate = false): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT c.*, loc.name AS location_name, loc.tax_state AS location_tax_state, loc.premises_id,
               us.display_name AS started_by_name, ua.display_name AS approved_by_name
        FROM app.inventory_counts c
        JOIN app.locations loc ON loc.id = c.location_id
        LEFT JOIN app.users us ON us.id = c.started_by
        LEFT JOIN app.users ua ON ua.id = c.approved_by
        WHERE c.id = :id
    SQL . ($forUpdate ? ' FOR UPDATE OF c' : ''));
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    if ($row === false) {
        return null;
    }
    $row['lines'] = find_count_lines($pdo, $id);
    return $row;
}

function find_count_lines(PDO $pdo, int $countId): array
{
    $statement = $pdo->prepare(COUNT_LINE_SELECT . ' WHERE l.count_id = :id ORDER BY i.code, lot.lot_number, l.id');
    $statement->execute(['id' => $countId]);
    return $statement->fetchAll();
}

function find_count_line(PDO $pdo, int $lineId): ?array
{
    $statement = $pdo->prepare(COUNT_LINE_SELECT . ' WHERE l.id = :id');
    $statement->execute(['id' => $lineId]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** Create a count with one expected line per non-zero lot balance at the location; the status ends at counting. */
function insert_count(PDO $pdo, int $locationId, string $kind, ?string $notes, int $startedBy): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.inventory_counts (number, location_id, kind, status, notes, started_by)
        VALUES (app.next_number('count'), :loc, :kind, 'open', :notes, :by)
        RETURNING id, number, status, location_id, kind
    SQL);
    $statement->execute(['loc' => $locationId, 'kind' => $kind, 'notes' => $notes, 'by' => $startedBy]);
    $row = $statement->fetch();
    $lines = $pdo->prepare(<<<'SQL'
        INSERT INTO app.inventory_count_lines (count_id, item_id, lot_id, qty_expected_base)
        SELECT :id, item_id, lot_id, qty_on_hand FROM app.v_lot_balances WHERE location_id = :loc AND qty_on_hand <> 0
    SQL);
    $lines->execute(['id' => $row['id'], 'loc' => $locationId]);
    $row['line_count'] = $lines->rowCount();
    $pdo->prepare("UPDATE app.inventory_counts SET status = 'counting' WHERE id = :id")->execute(['id' => $row['id']]);
    $row['status'] = 'counting';
    return $row;
}

/** Record the counted quantity (base units) on a line of an open or counting count. Returns the line before/after. */
function record_count_line(PDO $pdo, int $lineId, float $qtyCountedBase, ?string $note, int $countedBy): array
{
    $before = find_count_line($pdo, $lineId) ?? throw new RuntimeException('That count line does not exist.');
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.inventory_count_lines SET qty_counted_base = :qty, note = :note, counted_by = :by, counted_at = now()
        WHERE id = :id AND count_id IN (SELECT id FROM app.inventory_counts WHERE status IN ('open', 'counting'))
    SQL);
    $statement->execute(['id' => $lineId, 'qty' => $qtyCountedBase, 'note' => $note, 'by' => $countedBy]);
    if ($statement->rowCount() !== 1) {
        throw new RuntimeException('This count is no longer open for counting.');
    }
    return ['before' => $before, 'after' => find_count_line($pdo, $lineId)];
}

/** A lot found that had no expected balance: expected quantity zero. */
function add_count_line(PDO $pdo, int $countId, int $itemId, int $lotId): array
{
    $count = find_count($pdo, $countId, true) ?? throw new RuntimeException('That count does not exist.');
    if (!in_array($count['status'], ['open', 'counting'], true)) {
        throw new RuntimeException('This count is no longer open for counting.');
    }
    $lot = $pdo->prepare('SELECT item_id FROM app.lots WHERE id = :id');
    $lot->execute(['id' => $lotId]);
    if ((int) $lot->fetchColumn() !== $itemId) {
        throw new RuntimeException('Choose a lot of that item.');
    }
    $statement = $pdo->prepare('INSERT INTO app.inventory_count_lines (count_id, item_id, lot_id, qty_expected_base) VALUES (:c, :item, :lot, 0) ON CONFLICT (count_id, item_id, lot_id) DO NOTHING RETURNING id');
    $statement->execute(['c' => $countId, 'item' => $itemId, 'lot' => $lotId]);
    $id = $statement->fetchColumn();
    if ($id === false) {
        throw new RuntimeException('That lot is already on the count sheet.');
    }
    return find_count_line($pdo, (int) $id);
}

/** counting -> review once every line has a counted quantity. */
function submit_count(PDO $pdo, int $id, int $actorId): array
{
    $count = find_count($pdo, $id, true) ?? throw new RuntimeException('That count does not exist.');
    if ($count['status'] !== 'counting') {
        throw new RuntimeException('Count ' . $count['number'] . ' is ' . $count['status'] . ' and cannot be submitted.');
    }
    $missing = array_filter($count['lines'], static fn($l) => $l['qty_counted_base'] === null);
    if ($missing !== []) {
        throw new RuntimeException(count($missing) . ' line(s) still need a counted quantity.');
    }
    $pdo->prepare("UPDATE app.inventory_counts SET status = 'review' WHERE id = :id")->execute(['id' => $id]);
    return ['number' => $count['number'], 'lines' => count($count['lines']), 'variance_lines' => count(array_filter($count['lines'], static fn($l) => (float) $l['variance_base'] !== 0.0))];
}

/** review -> approved; one count_correction ledger row per line with a variance, all in one group. */
function approve_count(PDO $pdo, int $id, int $actorId): array
{
    $count = find_count($pdo, $id, true) ?? throw new RuntimeException('That count does not exist.');
    if ($count['status'] !== 'review') {
        throw new RuntimeException('Count ' . $count['number'] . ' is ' . $count['status'] . ' and cannot be approved.');
    }
    $reason = find_reason_code_by_code($pdo, 'COUNT') ?? throw new RuntimeException('The COUNT reason code is missing.');
    $completed = $pdo->query('SELECT now()')->fetchColumn();
    $group = new_group_id();
    $rows = [];
    foreach ($count['lines'] as $line) {
        $variance = (float) $line['variance_base'];
        if ($variance === 0.0) {
            continue;
        }
        $txn = insert_inventory_transaction($pdo, $group, 'count_correction', (int) $line['item_id'], (int) $line['lot_id'], (int) $count['location_id'], (int) $count['premises_id'],
            $variance, (float) $line['unit_cost_base'], 'none', null, (int) $reason['id'], $variance < 0 ? 'shortage' : 'inventory_gain', 'count', $id,
            'count:' . $id . ':' . $line['id'] . ':count', (string) $completed, $actorId);
        $rows[] = ['ledger_id' => $txn['id'], 'lot' => $line['lot_number'], 'variance_base' => $variance, 'ttb_category' => $txn['ttb_category']];
    }
    $pdo->prepare("UPDATE app.inventory_counts SET status = 'approved', approved_by = :by, approved_at = now(), completed_at = now() WHERE id = :id")->execute(['id' => $id, 'by' => $actorId]);
    return ['number' => $count['number'], 'group_id' => $group, 'variance_lines' => $rows];
}

function cancel_count(PDO $pdo, int $id, int $actorId): bool
{
    $statement = $pdo->prepare("UPDATE app.inventory_counts SET status = 'cancelled' WHERE id = :id AND status IN ('open', 'counting', 'review')");
    $statement->execute(['id' => $id]);
    return $statement->rowCount() === 1;
}

/** Any lot of an item, for the add-line picker. */
function find_count_lots(PDO $pdo, int $itemId, int $locationId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT l.id AS lot_id, l.lot_number, l.quality_status, COALESCE(b.qty_on_hand, 0) AS qty_here, i.base_unit_code, i.item_class
        FROM app.lots l JOIN app.items i ON i.id = l.item_id
        LEFT JOIN app.inventory_balances b ON b.lot_id = l.id AND b.location_id = :loc
        WHERE l.item_id = :item ORDER BY l.lot_number
    SQL);
    $statement->execute(['item' => $itemId, 'loc' => $locationId]);
    return $statement->fetchAll();
}

/** Count header without lines (cheap lookup for line-level endpoints). */
function find_count_line_owner(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT id, number, status, location_id FROM app.inventory_counts WHERE id = :id');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}
