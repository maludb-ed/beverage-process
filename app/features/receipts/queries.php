<?php
declare(strict_types=1);

require_once __DIR__ . '/../lots/queries.php';
require_once __DIR__ . '/../inventory/ledger.php';

const RECEIPT_STATUSES = ['draft' => 'Draft', 'posted' => 'Posted', 'cancelled' => 'Cancelled'];
const RECEIPT_SORTS = ['number' => 'gr.number', 'received_at' => 'gr.received_at', 'status' => 'gr.status', 'supplier' => 's.name'];
const DISCREPANCY_KINDS = ['none' => 'None', 'short' => 'Short', 'over' => 'Over', 'damaged' => 'Damaged', 'substituted' => 'Substituted'];

function find_receipts(PDO $pdo, string $search = '', string $sort = '-received_at', int $page = 1, ?string $status = null, ?int $supplierId = null): array
{
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(gr.number ILIKE :s OR s.name ILIKE :s OR gr.delivery_note_ref ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    if ($status) { $where[] = 'gr.status = :status'; $params['status'] = $status; }
    if ($supplierId !== null) { $where[] = 'gr.supplier_id = :sid'; $params['sid'] = $supplierId; }
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    $from = ' FROM app.goods_receipts gr JOIN app.suppliers s ON s.id = gr.supplier_id LEFT JOIN app.purchase_orders po ON po.id = gr.purchase_order_id';
    return paged_query($pdo,
        'SELECT gr.id, gr.number, gr.status, gr.received_at, gr.supplier_id, s.name AS supplier_name, gr.purchase_order_id, po.number AS po_number,
                (SELECT count(*) FROM app.goods_receipt_lines l WHERE l.goods_receipt_id = gr.id) AS line_count'
            . $from . $whereSql . ' ORDER BY ' . order_by($sort, RECEIPT_SORTS, '-received_at'),
        'SELECT count(*)' . $from . $whereSql, $params, $page);
}

function find_receipt(PDO $pdo, int $id, bool $forUpdate = false): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT gr.*, s.name AS supplier_name, po.number AS po_number, p.name AS premises_name, loc.name AS receiving_location_name,
               loc.tax_state AS receiving_tax_state, ur.display_name AS received_by_name, up.display_name AS posted_by_name
        FROM app.goods_receipts gr
        JOIN app.suppliers s ON s.id = gr.supplier_id
        JOIN app.premises p ON p.id = gr.premises_id
        JOIN app.locations loc ON loc.id = gr.receiving_location_id
        LEFT JOIN app.purchase_orders po ON po.id = gr.purchase_order_id
        LEFT JOIN app.users ur ON ur.id = gr.received_by
        LEFT JOIN app.users up ON up.id = gr.posted_by
        WHERE gr.id = :id
    SQL . ($forUpdate ? ' FOR UPDATE OF gr' : ''));
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function find_receipt_lines(PDO $pdo, int $receiptId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT l.*, i.code AS item_code, i.name AS item_name, i.item_class, i.base_unit_code, i.catch_weight, i.default_receipt_status, i.shelf_life_days,
               lot.lot_number, lot.quality_status, pl.line_no AS po_line_no, ploc.name AS putaway_location_name,
               w.id AS weigh_tag_id, w.tag_number, w.gross_kg, w.tare_kg, w.net_kg, w.bin_count, w.variety, w.orchard, w.block,
               w.brix_at_receipt, w.condition_note
        FROM app.goods_receipt_lines l
        JOIN app.items i ON i.id = l.item_id
        LEFT JOIN app.lots lot ON lot.id = l.lot_id
        LEFT JOIN app.purchase_order_lines pl ON pl.id = l.purchase_order_line_id
        LEFT JOIN app.locations ploc ON ploc.id = l.putaway_location_id
        LEFT JOIN app.weigh_tags w ON w.goods_receipt_line_id = l.id
        WHERE l.goods_receipt_id = :id ORDER BY l.line_no
    SQL);
    $statement->execute(['id' => $receiptId]);
    return $statement->fetchAll();
}

function find_open_po_lines(PDO $pdo, int $purchaseOrderId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT v.*, pl.purchase_unit_code, pl.to_base_factor, pl.unit_price
        FROM app.v_open_po_lines v JOIN app.purchase_order_lines pl ON pl.id = v.line_id
        WHERE v.purchase_order_id = :id ORDER BY v.line_no
    SQL);
    $statement->execute(['id' => $purchaseOrderId]);
    return $statement->fetchAll();
}

/** Open or partially received orders of one supplier, id => number. */
function open_purchase_order_options(PDO $pdo, ?int $supplierId): array
{
    if ($supplierId === null) {
        return [];
    }
    $statement = $pdo->prepare("SELECT id, number FROM app.purchase_orders WHERE supplier_id = :s AND status IN ('open', 'partial') ORDER BY number");
    $statement->execute(['s' => $supplierId]);
    return array_column($statement->fetchAll(), 'number', 'id');
}

/** Receiving locations of a premises (kind receiving first; any active location as fallback). */
function receiving_location_options(PDO $pdo, ?int $premisesId = null): array
{
    $sql = "SELECT l.id, p.name || ' — ' || l.name AS label FROM app.locations l JOIN app.premises p ON p.id = l.premises_id
            WHERE l.active AND (:pid::bigint IS NULL OR l.premises_id = :pid::bigint)";
    $statement = $pdo->prepare($sql . " AND l.kind = 'receiving' ORDER BY label");
    $statement->execute(['pid' => $premisesId]);
    $rows = $statement->fetchAll();
    if ($rows === []) {
        $statement = $pdo->prepare($sql . ' ORDER BY label');
        $statement->execute(['pid' => $premisesId]);
        $rows = $statement->fetchAll();
    }
    return array_column($rows, 'label', 'id');
}

/** Putaway destinations: active locations of the premises with the same tax state. */
function putaway_location_options(PDO $pdo, int $premisesId, string $taxState): array
{
    $statement = $pdo->prepare("SELECT id, name FROM app.locations WHERE active AND premises_id = :p AND tax_state = :t AND kind <> 'outside' ORDER BY name");
    $statement->execute(['p' => $premisesId, 't' => $taxState]);
    return array_column($statement->fetchAll(), 'name', 'id');
}

function insert_receipt(PDO $pdo, int $premisesId, int $supplierId, ?int $purchaseOrderId, string $receivedAt, int $receivingLocationId, ?string $deliveryNoteRef, ?string $notes, int $receivedBy): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.goods_receipts (number, premises_id, supplier_id, purchase_order_id, received_at, receiving_location_id, delivery_note_ref, notes, received_by)
        VALUES (app.next_number('receipt'), :premises_id, :supplier_id, :po, :received_at, :loc, :ref, :notes, :by)
        RETURNING id, number, status, supplier_id, purchase_order_id, received_at, receiving_location_id
    SQL);
    $statement->execute(['premises_id' => $premisesId, 'supplier_id' => $supplierId, 'po' => $purchaseOrderId, 'received_at' => $receivedAt,
        'loc' => $receivingLocationId, 'ref' => $deliveryNoteRef, 'notes' => $notes, 'by' => $receivedBy]);
    return $statement->fetch();
}

function update_receipt(PDO $pdo, int $id, int $premisesId, int $supplierId, ?int $purchaseOrderId, string $receivedAt, int $receivingLocationId, ?string $deliveryNoteRef, ?string $notes): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.goods_receipts SET premises_id = :premises_id, supplier_id = :supplier_id, purchase_order_id = :po, received_at = :received_at,
               receiving_location_id = :loc, delivery_note_ref = :ref, notes = :notes
        WHERE id = :id AND status = 'draft'
        RETURNING id, number, status, supplier_id, purchase_order_id, received_at, receiving_location_id
    SQL);
    $statement->execute(['id' => $id, 'premises_id' => $premisesId, 'supplier_id' => $supplierId, 'po' => $purchaseOrderId, 'received_at' => $receivedAt,
        'loc' => $receivingLocationId, 'ref' => $deliveryNoteRef, 'notes' => $notes]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Only draft receipts can be edited.');
    }
    return $row;
}

/**
 * Draft only. Each line: purchase_order_line_id, item_id, qty_received, purchase_unit_code, to_base_factor, qty_base,
 * unit_cost_base, discrepancy_kind, discrepancy_note, supplier_lot_number, expires_on, notes, weigh_tag (array|null with kg values).
 */
function replace_receipt_lines(PDO $pdo, int $receiptId, array $lines): void
{
    $pdo->prepare('DELETE FROM app.goods_receipt_lines WHERE goods_receipt_id = :id')->execute(['id' => $receiptId]);
    $insert = $pdo->prepare(<<<'SQL'
        INSERT INTO app.goods_receipt_lines (goods_receipt_id, line_no, purchase_order_line_id, item_id, qty_received, purchase_unit_code, to_base_factor,
                                             qty_base, unit_cost_base, discrepancy_kind, discrepancy_note, supplier_lot_number, expires_on, notes)
        VALUES (:r, :line_no, :pol, :item, :qty, :unit, :factor, :qty_base, :cost, :dk, :dn, :sln, :exp, :notes)
        RETURNING id
    SQL);
    $tag = $pdo->prepare(<<<'SQL'
        INSERT INTO app.weigh_tags (goods_receipt_line_id, tag_number, gross_kg, tare_kg, bin_count, variety, orchard, block, brix_at_receipt, condition_note, weighed_by)
        VALUES (:line, :tag, :gross, :tare, :bins, :variety, :orchard, :block, :brix, :cond, :by)
    SQL);
    foreach (array_values($lines) as $index => $line) {
        $insert->execute([
            'r' => $receiptId, 'line_no' => $index + 1, 'pol' => $line['purchase_order_line_id'], 'item' => $line['item_id'],
            'qty' => $line['qty_received'], 'unit' => $line['purchase_unit_code'], 'factor' => $line['to_base_factor'], 'qty_base' => $line['qty_base'],
            'cost' => $line['unit_cost_base'], 'dk' => $line['discrepancy_kind'], 'dn' => $line['discrepancy_note'], 'sln' => $line['supplier_lot_number'],
            'exp' => $line['expires_on'], 'notes' => $line['notes'],
        ]);
        $lineId = (int) $insert->fetchColumn();
        if (!empty($line['weigh_tag'])) {
            $w = $line['weigh_tag'];
            $tag->execute(['line' => $lineId, 'tag' => $w['tag_number'], 'gross' => $w['gross_kg'], 'tare' => $w['tare_kg'], 'bins' => $w['bin_count'],
                'variety' => $w['variety'], 'orchard' => $w['orchard'], 'block' => $w['block'], 'brix' => $w['brix_at_receipt'], 'cond' => $w['condition_note'], 'by' => $w['weighed_by']]);
        }
    }
}

function set_receipt_line_lot(PDO $pdo, int $lineId, int $lotId): void
{
    $pdo->prepare('UPDATE app.goods_receipt_lines SET lot_id = :lot WHERE id = :id')->execute(['lot' => $lotId, 'id' => $lineId]);
}

function post_receipt_line_to_po(PDO $pdo, int $purchaseOrderLineId, float $qtyBase): void
{
    $pdo->prepare(<<<'SQL'
        UPDATE app.purchase_order_lines
        SET qty_received_base = qty_received_base + :qty,
            status = CASE WHEN qty_received_base + :qty >= qty_ordered_base THEN 'received' ELSE 'partial' END
        WHERE id = :id AND status IN ('open', 'partial')
    SQL)->execute(['qty' => $qtyBase, 'id' => $purchaseOrderLineId]);
}

/** closed when every line is received/closed_short/cancelled, partial when anything arrived, else unchanged. */
function recompute_purchase_order_status(PDO $pdo, int $purchaseOrderId): string
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.purchase_orders po SET status = CASE
            WHEN NOT EXISTS (SELECT 1 FROM app.purchase_order_lines l WHERE l.purchase_order_id = po.id AND l.status IN ('open', 'partial')) THEN 'closed'
            WHEN EXISTS (SELECT 1 FROM app.purchase_order_lines l WHERE l.purchase_order_id = po.id AND l.qty_received_base > 0) THEN 'partial'
            ELSE po.status END
        WHERE po.id = :id AND po.status IN ('open', 'partial')
        RETURNING status
    SQL);
    $statement->execute(['id' => $purchaseOrderId]);
    return (string) ($statement->fetchColumn() ?: '');
}

function mark_receipt_posted(PDO $pdo, int $id, int $postedBy): array
{
    $statement = $pdo->prepare("UPDATE app.goods_receipts SET status = 'posted', posted_by = :by, posted_at = now() WHERE id = :id AND status = 'draft' RETURNING id, number, status, posted_at");
    $statement->execute(['id' => $id, 'by' => $postedBy]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Only a draft receipt can be posted.');
    }
    return $row;
}

function update_receipt_line_putaway(PDO $pdo, int $lineId, int $locationId): void
{
    $pdo->prepare('UPDATE app.goods_receipt_lines SET putaway_location_id = :loc WHERE id = :id')->execute(['loc' => $locationId, 'id' => $lineId]);
}

/**
 * Post a draft receipt: lots, weigh-tag attributes, ledger receipts, PO quantities,
 * status. Caller owns the transaction and the activity log row. Returns the lots created.
 */
function post_receipt(PDO $pdo, array $receipt, array $lines, int $actorId): array
{
    $groupId = new_group_id();
    $lots = [];
    $touchedPo = false;
    foreach ($lines as $line) {
        if ((float) $line['qty_base'] <= 0) {
            continue;
        }
        $lot = insert_lot($pdo, '', (int) $line['item_id'], (int) $receipt['premises_id'], (int) $receipt['supplier_id'], $line['supplier_lot_number'],
            substr((string) $receipt['received_at'], 0, 10), $line['expires_on'], (string) $line['default_receipt_status'],
            (float) $line['unit_cost_base'], 'receipt_line', (int) $line['id'], $actorId);
        set_receipt_line_lot($pdo, (int) $line['id'], (int) $lot['id']);
        if ($line['weigh_tag_id'] !== null) {
            foreach (['variety' => 'variety', 'orchard' => 'orchard', 'block' => 'block'] as $key => $column) {
                if ($line[$column] !== null && $line[$column] !== '') {
                    set_lot_attribute($pdo, (int) $lot['id'], $key, null, (string) $line[$column], null, 'weigh_tag', $actorId);
                }
            }
            if ($line['brix_at_receipt'] !== null) { set_lot_attribute($pdo, (int) $lot['id'], 'brix', (float) $line['brix_at_receipt'], null, '°Bx', 'weigh_tag', $actorId); }
            if ($line['bin_count'] !== null) { set_lot_attribute($pdo, (int) $lot['id'], 'bin_count', (float) $line['bin_count'], null, 'ea', 'weigh_tag', $actorId); }
            set_lot_attribute($pdo, (int) $lot['id'], 'net_kg', (float) $line['net_kg'], null, 'kg', 'weigh_tag', $actorId);
        }
        insert_inventory_transaction($pdo, $groupId, 'receipt', (int) $line['item_id'], (int) $lot['id'], (int) $receipt['receiving_location_id'],
            (int) $receipt['premises_id'], (float) $line['qty_base'], (float) $line['unit_cost_base'], 'supplier', (int) $receipt['supplier_id'], null,
            'received', 'goods_receipt', (int) $receipt['id'], 'receipt:' . $receipt['id'] . ':line:' . $line['id'], (string) $receipt['received_at'], $actorId);
        if ($line['purchase_order_line_id'] !== null) {
            post_receipt_line_to_po($pdo, (int) $line['purchase_order_line_id'], (float) $line['qty_base']);
            $touchedPo = true;
        }
        $lots[] = ['lot_id' => (int) $lot['id'], 'lot_number' => $lot['lot_number'], 'item' => $line['item_code'], 'qty_base' => (float) $line['qty_base'], 'quality_status' => $lot['quality_status']];
    }
    if ($touchedPo && $receipt['purchase_order_id'] !== null) {
        recompute_purchase_order_status($pdo, (int) $receipt['purchase_order_id']);
    }
    mark_receipt_posted($pdo, (int) $receipt['id'], $actorId);
    return $lots;
}

/** Every open or partial order, "PO-00001 — Supplier"; includes $keepId even if no longer open. */
function all_open_purchase_order_options(PDO $pdo, ?int $keepId = null): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT po.id, po.number || ' — ' || s.name AS label FROM app.purchase_orders po JOIN app.suppliers s ON s.id = po.supplier_id
        WHERE po.status IN ('open', 'partial') OR po.id = :keep ORDER BY po.number
    SQL);
    $statement->execute(['keep' => $keepId]);
    return array_column($statement->fetchAll(), 'label', 'id');
}

/** Posted lines whose lot still has stock at the receiving location, keyed n{line_no}. */
function putaway_rows(PDO $pdo, array $receipt): array
{
    $rows = [];
    foreach (find_receipt_lines($pdo, (int) $receipt['id']) as $line) {
        if ($line['lot_id'] === null) {
            continue;
        }
        $onHand = lot_on_hand($pdo, (int) $line['lot_id'], (int) $receipt['receiving_location_id']);
        if ($onHand > 0) {
            $rows['n' . $line['line_no']] = $line + ['line_id' => (int) $line['id'], 'qty_on_hand' => $onHand];
        }
    }
    return $rows;
}
