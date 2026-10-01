<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/receipts/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/receipts/validation.php';
require_once dirname(__DIR__, 2) . '/app/features/purchase-orders/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';

require_post();
verify_csrf();
$user = require_role('receiving');
$pdo = db();

$id = request_integer('id');
$catalog = purchasing_item_catalog($pdo);
$suppliers = purchasing_supplier_options($pdo);
$premises = premises_options($pdo);
$receivedAtRaw = request_string('received_at', 20);
$receivedAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $receivedAtRaw, new DateTimeZone((string) config('app.timezone')));
$receipt = [
    'id' => $id,
    'purchase_order_id' => request_integer('purchase_order_id'),
    'supplier_id' => request_integer('supplier_id'),
    'premises_id' => request_integer('premises_id'),
    'receiving_location_id' => request_integer('receiving_location_id'),
    'received_at' => $receivedAtRaw,
    'delivery_note_ref' => request_string('delivery_note_ref', 80),
    'notes' => request_string('notes', 2000),
];
$errors = [];
$order = null;
if ($receipt['purchase_order_id'] !== null) {
    $order = find_purchase_order($pdo, $receipt['purchase_order_id']);
    if ($order === null || !in_array($order['status'], ['open', 'partial'], true)) {
        $errors['purchase_order_id'] = 'That order is not open for receiving.';
    } else {
        $receipt['supplier_id'] = (int) $order['supplier_id'];   // the order decides the supplier
    }
}
if ($receipt['supplier_id'] === null || !isset($suppliers[$receipt['supplier_id']])) { $errors['supplier_id'] = 'Choose a supplier.'; }
if ($receipt['premises_id'] === null || !isset($premises[$receipt['premises_id']])) { $errors['premises_id'] = 'Choose a premises.'; }
$locations = receiving_location_options($pdo, $receipt['premises_id']);
if ($receipt['receiving_location_id'] === null || !isset($locations[$receipt['receiving_location_id']])) { $errors['receiving_location_id'] = 'Choose where the delivery was received.'; }
if ($receivedAt === false) { $errors['received_at'] = 'Enter when the delivery arrived.'; }

$rawLines = is_array($_POST['lines'] ?? null) ? $_POST['lines'] : [];
[$lines, $lineErrors] = validate_receipt_lines($rawLines, $catalog, (int) $user['id']);
if ($lines === []) { $errors['lines'] = 'Add at least one line.'; }
if ($lineErrors !== []) { $errors['line_rows'] = 'Fix the highlighted lines.'; }
if ($order !== null) {
    $orderLineIds = array_map('intval', array_column(find_purchase_order_lines($pdo, (int) $order['id']), 'id'));
    foreach ($lines as $n => $line) {
        if ($line['purchase_order_line_id'] !== null && !in_array($line['purchase_order_line_id'], $orderLineIds, true)) {
            $lines[$n]['purchase_order_line_id'] = null;    // a stale line from another order becomes unplanned
        }
    }
} else {
    foreach ($lines as $n => $line) {
        $lines[$n]['purchase_order_line_id'] = null;
    }
}

$before = null;
if ($id !== null) {
    $before = find_receipt($pdo, $id) ?? not_found('That receipt does not exist.');
    if ($before['status'] !== 'draft') { $errors['form'] = 'Only draft receipts can be edited.'; }
    $receipt['number'] = $before['number'];
}

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $at = $receivedAt->format(DATE_ATOM);
        $saved = $id === null
            ? insert_receipt($pdo, $receipt['premises_id'], $receipt['supplier_id'], $receipt['purchase_order_id'], $at, $receipt['receiving_location_id'], $receipt['delivery_note_ref'] ?: null, $receipt['notes'] ?: null, (int) $user['id'])
            : update_receipt($pdo, $id, $receipt['premises_id'], $receipt['supplier_id'], $receipt['purchase_order_id'], $at, $receipt['receiving_location_id'], $receipt['delivery_note_ref'] ?: null, $receipt['notes'] ?: null);
        replace_receipt_lines($pdo, (int) $saved['id'], $lines);
        $summary = array_map(static fn($l) => ['item_id' => $l['item_id'], 'qty_base' => round((float) $l['qty_base'], 3), 'weigh_tag' => $l['weigh_tag'] !== null], array_values($lines));
        log_activity($pdo, $id === null ? 'receipt_created' : 'receipt_updated', 'goods_receipt', (int) $saved['id'], $saved['number'],
            $before === null ? null : array_intersect_key($before, $saved), $saved + ['lines' => $summary], [], $id === null ? 'receipt-add' : 'receipt-edit');
        if (array_filter($lines, static fn($l) => $l['weigh_tag'] !== null) !== []) {
            log_activity($pdo, 'weigh_tag_recorded', 'goods_receipt', (int) $saved['id'], $saved['number'], null, null,
                ['tags' => count(array_filter($lines, static fn($l) => $l['weigh_tag'] !== null))], $id === null ? 'receipt-add' : 'receipt-edit');
        }
        $pdo->commit();
        flash('success', 'Receipt ' . $saved['number'] . ' saved as a draft. Post it to create the lots.');
        hx_trigger('receiptsChanged');
        hx_location('/receipts/' . $saved['id']);
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The receipt could not be saved.') : $exception->getMessage();
    }
}
http_response_code(422);
render_screen($id ? 'Edit ' . $receipt['number'] : 'Add Receipt', $id ? 'receipt-edit' : 'receipt-add', view('receipts/partials/form.php', [
    'receipt' => $receipt, 'lines' => $lines === [] ? ['n1' => []] : $lines, 'errors' => $errors, 'lineErrors' => $lineErrors, 'catalog' => $catalog,
    'suppliers' => $suppliers, 'premises' => $premises, 'locations' => $locations, 'openOrders' => all_open_purchase_order_options($pdo, $receipt['purchase_order_id']),
]), 'goods_receipt', $id);
