<?php
declare(strict_types=1);

const PO_STATUSES = ['draft' => 'Draft', 'open' => 'Open', 'partial' => 'Partial', 'closed' => 'Closed', 'closed_short' => 'Closed short', 'cancelled' => 'Cancelled'];
const PO_SORTS = ['number' => 'po.number', 'expected_on' => 'po.expected_on', 'status' => 'po.status', 'supplier' => 's.name', 'ordered_on' => 'po.ordered_on'];
const PURCHASABLE_CLASSES = ['fruit', 'juice', 'yeast', 'additive', 'packaging', 'consumable', 'returnable_asset'];

function find_purchase_orders(PDO $pdo, string $search = '', string $sort = '-number', int $page = 1, ?string $status = null, ?int $supplierId = null): array
{
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(po.number ILIKE :s OR s.name ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    if ($status !== null && $status !== '') {
        $where[] = 'po.status = :status';
        $params['status'] = $status;
    }
    if ($supplierId !== null) {
        $where[] = 'po.supplier_id = :supplier_id';
        $params['supplier_id'] = $supplierId;
    }
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    $from = ' FROM app.purchase_orders po JOIN app.suppliers s ON s.id = po.supplier_id';
    return paged_query(
        $pdo,
        'SELECT po.id, po.number, po.status, po.ordered_on, po.expected_on, po.supplier_id, s.name AS supplier_name,
                (SELECT count(*) FROM app.purchase_order_lines l WHERE l.purchase_order_id = po.id) AS line_count,
                (po.expected_on < current_date AND po.status IN (\'open\', \'partial\')) AS overdue'
            . $from . $whereSql . ' ORDER BY ' . order_by($sort, PO_SORTS, '-number'),
        'SELECT count(*)' . $from . $whereSql,
        $params,
        $page
    );
}

function find_purchase_order(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT po.*, s.name AS supplier_name, pr.name AS premises_name,
               ua.display_name AS approved_by_name, uc.display_name AS created_by_name,
               (po.expected_on < current_date AND po.status IN ('open', 'partial')) AS overdue
        FROM app.purchase_orders po
        JOIN app.suppliers s ON s.id = po.supplier_id
        JOIN app.premises pr ON pr.id = po.premises_id
        LEFT JOIN app.users ua ON ua.id = po.approved_by
        LEFT JOIN app.users uc ON uc.id = po.created_by
        WHERE po.id = :id
    SQL);
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function find_purchase_order_by_number(PDO $pdo, string $number): ?array
{
    $statement = $pdo->prepare('SELECT id FROM app.purchase_orders WHERE number = :n');
    $statement->execute(['n' => $number]);
    $id = $statement->fetchColumn();
    return $id === false ? null : find_purchase_order($pdo, (int) $id);
}

function find_purchase_order_lines(PDO $pdo, int $purchaseOrderId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT l.*, i.code AS item_code, i.name AS item_name, i.base_unit_code, i.item_class,
               (l.qty_ordered_base - l.qty_received_base) AS qty_outstanding_base, rc.name AS close_reason
        FROM app.purchase_order_lines l
        JOIN app.items i ON i.id = l.item_id
        LEFT JOIN app.reason_codes rc ON rc.id = l.close_reason_code_id
        WHERE l.purchase_order_id = :id
        ORDER BY l.line_no
    SQL);
    $statement->execute(['id' => $purchaseOrderId]);
    return $statement->fetchAll();
}

function find_receipts_for_po(PDO $pdo, int $purchaseOrderId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT gr.id, gr.number, gr.status, gr.received_at, gr.delivery_note_ref,
               (SELECT count(*) FROM app.goods_receipt_lines l WHERE l.goods_receipt_id = gr.id) AS line_count
        FROM app.goods_receipts gr WHERE gr.purchase_order_id = :id ORDER BY gr.received_at DESC
    SQL);
    $statement->execute(['id' => $purchaseOrderId]);
    return $statement->fetchAll();
}

function insert_purchase_order(PDO $pdo, int $supplierId, int $premisesId, ?string $orderedOn, ?string $expectedOn, ?string $notes, int $createdBy): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.purchase_orders (number, supplier_id, premises_id, status, ordered_on, expected_on, notes, created_by)
        VALUES (app.next_number('po'), :supplier_id, :premises_id, 'draft', :ordered_on, :expected_on, :notes, :created_by)
        RETURNING id, number, supplier_id, premises_id, status, ordered_on, expected_on, notes
    SQL);
    $statement->execute(['supplier_id' => $supplierId, 'premises_id' => $premisesId, 'ordered_on' => $orderedOn, 'expected_on' => $expectedOn, 'notes' => $notes, 'created_by' => $createdBy]);
    return $statement->fetch();
}

function update_purchase_order(PDO $pdo, int $id, int $supplierId, int $premisesId, ?string $orderedOn, ?string $expectedOn, ?string $notes): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.purchase_orders
        SET supplier_id = :supplier_id, premises_id = :premises_id, ordered_on = :ordered_on, expected_on = :expected_on, notes = :notes
        WHERE id = :id AND status = 'draft'
        RETURNING id, number, supplier_id, premises_id, status, ordered_on, expected_on, notes
    SQL);
    $statement->execute(['id' => $id, 'supplier_id' => $supplierId, 'premises_id' => $premisesId, 'ordered_on' => $orderedOn, 'expected_on' => $expectedOn, 'notes' => $notes]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Only draft purchase orders can be edited.');
    }
    return $row;
}

/** Draft only. Each line: item_id, purchase_unit_code, to_base_factor, qty_ordered, unit_price, expected_on. */
function replace_purchase_order_lines(PDO $pdo, int $purchaseOrderId, array $lines): void
{
    $pdo->prepare('DELETE FROM app.purchase_order_lines WHERE purchase_order_id = :id')->execute(['id' => $purchaseOrderId]);
    $insert = $pdo->prepare(<<<'SQL'
        INSERT INTO app.purchase_order_lines (purchase_order_id, line_no, item_id, qty_ordered, purchase_unit_code, to_base_factor, unit_price, expected_on)
        VALUES (:po, :line_no, :item_id, :qty_ordered, :purchase_unit_code, :to_base_factor, :unit_price, :expected_on)
    SQL);
    foreach (array_values($lines) as $index => $line) {
        $insert->execute([
            'po' => $purchaseOrderId, 'line_no' => $index + 1, 'item_id' => $line['item_id'], 'qty_ordered' => $line['qty_ordered'],
            'purchase_unit_code' => $line['purchase_unit_code'], 'to_base_factor' => $line['to_base_factor'],
            'unit_price' => $line['unit_price'], 'expected_on' => $line['expected_on'],
        ]);
    }
}

function approve_purchase_order(PDO $pdo, int $id, int $approvedBy): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.purchase_orders SET status = 'open', approved_by = :by, approved_at = now(), ordered_on = COALESCE(ordered_on, current_date)
        WHERE id = :id AND status = 'draft' AND EXISTS (SELECT 1 FROM app.purchase_order_lines WHERE purchase_order_id = :id)
        RETURNING id, number, status
    SQL);
    $statement->execute(['id' => $id, 'by' => $approvedBy]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Only a draft order with at least one line can be approved.');
    }
    return $row;
}

function close_purchase_order_short(PDO $pdo, int $id, int $reasonCodeId): array
{
    $pdo->prepare(<<<'SQL'
        UPDATE app.purchase_order_lines SET status = 'closed_short', close_reason_code_id = :reason
        WHERE purchase_order_id = :id AND status IN ('open', 'partial')
    SQL)->execute(['id' => $id, 'reason' => $reasonCodeId]);
    $statement = $pdo->prepare("UPDATE app.purchase_orders SET status = 'closed_short' WHERE id = :id AND status IN ('open', 'partial') RETURNING id, number, status");
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Only an open or partially received order can be closed short.');
    }
    return $row;
}

function cancel_purchase_order(PDO $pdo, int $id): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.purchase_orders SET status = 'cancelled'
        WHERE id = :id AND status IN ('draft', 'open')
          AND NOT EXISTS (SELECT 1 FROM app.purchase_order_lines WHERE purchase_order_id = :id AND qty_received_base > 0)
          AND NOT EXISTS (SELECT 1 FROM app.goods_receipts WHERE purchase_order_id = :id AND status = 'posted')
        RETURNING id, number, status
    SQL);
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Only a draft or open order with nothing received can be cancelled.');
    }
    $pdo->prepare("UPDATE app.purchase_order_lines SET status = 'cancelled' WHERE purchase_order_id = :id")->execute(['id' => $id]);
    return $row;
}

/** Reason codes usable to close an order short. */
function short_close_reason_options(PDO $pdo): array
{
    return array_column($pdo->query("SELECT id, name FROM app.reason_codes WHERE applies_to = 'short_close' AND active ORDER BY name")->fetchAll(), 'name', 'id');
}

function purchasing_supplier_options(PDO $pdo): array
{
    return array_column($pdo->query('SELECT id, name FROM app.suppliers WHERE active ORDER BY name')->fetchAll(), 'name', 'id');
}

/**
 * Items that can be bought or received, with the units each can be entered in:
 * id => [code, name, item_class, base_unit_code, catch_weight, shelf_life_days,
 *        default_receipt_status, units => [code => [label, factor]]].
 * Units are the base unit, other units of the same dimension, and the item's own units.
 */
function purchasing_item_catalog(PDO $pdo, ?array $classes = PURCHASABLE_CLASSES): array
{
    $sql = 'SELECT id, code, name, item_class, base_unit_code, catch_weight, shelf_life_days, default_receipt_status FROM app.items WHERE active';
    $params = [];
    if ($classes !== null) {
        $placeholders = [];
        foreach (array_values($classes) as $i => $class) {
            $placeholders[] = ':c' . $i;
            $params['c' . $i] = $class;
        }
        $sql .= ' AND item_class IN (' . implode(', ', $placeholders) . ')';
    }
    $statement = $pdo->prepare($sql . ' ORDER BY code');
    $statement->execute($params);
    $items = [];
    foreach ($statement->fetchAll() as $row) {
        $row['units'] = [];
        $items[(int) $row['id']] = $row;
    }
    if ($items === []) {
        return [];
    }
    $units = unit_table();
    foreach ($items as $id => $item) {
        $dimension = $units[$item['base_unit_code']]['dimension'] ?? 'count';
        foreach ($units as $code => $unit) {
            if ($unit['dimension'] === $dimension && $code !== 'case') {
                $items[$id]['units'][$code] = ['label' => $code, 'factor' => (float) $unit['to_base_factor']];
            }
        }
    }
    foreach ($pdo->query('SELECT item_id, unit_code, unit_name, to_base_factor FROM app.item_units') as $row) {
        if (isset($items[(int) $row['item_id']])) {
            $items[(int) $row['item_id']]['units'][$row['unit_code']] = ['label' => $row['unit_code'] . ' (' . $row['unit_name'] . ')', 'factor' => (float) $row['to_base_factor']];
        }
    }
    return $items;
}

/** The supplier's own purchase unit and last price for an item, or null. */
function find_supplier_item_terms(PDO $pdo, int $supplierId, int $itemId): ?array
{
    $statement = $pdo->prepare('SELECT purchase_unit_code, to_base_factor, last_price, lead_time_days FROM app.supplier_items WHERE supplier_id = :s AND item_id = :i AND active');
    $statement->execute(['s' => $supplierId, 'i' => $itemId]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** The single active premises id when exactly one exists. */
function default_premises_id(PDO $pdo): ?int
{
    $ids = $pdo->query('SELECT id FROM app.premises WHERE active ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    return count($ids) === 1 ? (int) $ids[0] : null;
}

/** One supplier's delivery record from app.v_supplier_performance. */
function find_supplier_performance(PDO $pdo, int $supplierId): array
{
    $statement = $pdo->prepare('SELECT receipts, late_receipts, short_lines, damaged_lines FROM app.v_supplier_performance WHERE supplier_id = :id');
    $statement->execute(['id' => $supplierId]);
    return $statement->fetch() ?: ['receipts' => 0, 'late_receipts' => 0, 'short_lines' => 0, 'damaged_lines' => 0];
}
