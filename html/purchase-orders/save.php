<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/purchase-orders/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/purchase-orders/validation.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';

require_post();
verify_csrf();
$user = require_role('receiving');
$pdo = db();

$id = request_integer('id');
$catalog = purchasing_item_catalog($pdo);
$suppliers = purchasing_supplier_options($pdo);
$premises = premises_options($pdo);
$order = [
    'id' => $id,
    'supplier_id' => request_integer('supplier_id'),
    'premises_id' => request_integer('premises_id'),
    'ordered_on' => post_date('ordered_on'),
    'expected_on' => post_date('expected_on'),
    'notes' => request_string('notes', 2000),
];
$errors = [];
if ($order['supplier_id'] === null || !isset($suppliers[$order['supplier_id']])) { $errors['supplier_id'] = 'Choose a supplier.'; }
if ($order['premises_id'] === null || !isset($premises[$order['premises_id']])) { $errors['premises_id'] = 'Choose a premises.'; }
if ($order['ordered_on'] === false) { $errors['ordered_on'] = 'Use a valid date.'; }
if ($order['expected_on'] === false) { $errors['expected_on'] = 'Use a valid date.'; }

$rawLines = is_array($_POST['lines'] ?? null) ? $_POST['lines'] : [];
[$lines, $lineErrors] = validate_po_lines($rawLines, $catalog);
if ($lines === []) { $errors['lines'] = 'Add at least one line.'; }
if ($lineErrors !== []) { $errors['line_rows'] = 'Fix the highlighted lines.'; }

$before = null;
if ($id !== null) {
    $before = find_purchase_order($pdo, $id) ?? not_found('That purchase order does not exist.');
    if ($before['status'] !== 'draft') { $errors['form'] = 'Only draft purchase orders can be edited.'; }
    $order['number'] = $before['number'];
}

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $saved = $id === null
            ? insert_purchase_order($pdo, $order['supplier_id'], $order['premises_id'], $order['ordered_on'], $order['expected_on'], $order['notes'] ?: null, (int) $user['id'])
            : update_purchase_order($pdo, $id, $order['supplier_id'], $order['premises_id'], $order['ordered_on'], $order['expected_on'], $order['notes'] ?: null);
        replace_purchase_order_lines($pdo, (int) $saved['id'], $lines);
        log_activity($pdo, $id === null ? 'po_created' : 'po_updated', 'purchase_order', (int) $saved['id'], $saved['number'],
            $before === null ? null : array_intersect_key($before, $saved), $saved + ['lines' => array_values($lines)], [], $id === null ? 'purchase-order-add' : 'purchase-order-edit');
        $pdo->commit();
        flash('success', 'Purchase order ' . $saved['number'] . ' saved.');
        hx_trigger('purchaseOrdersChanged');
        hx_location('/purchase-orders/' . $saved['id']);
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The purchase order could not be saved.') : $exception->getMessage();
    }
}
http_response_code(422);
render_screen($id ? 'Edit ' . $order['number'] : 'Add Purchase Order', $id ? 'purchase-order-edit' : 'purchase-order-add', view('purchase-orders/partials/form.php', [
    'order' => $order, 'lines' => $lines === [] ? ['n1' => []] : $lines, 'errors' => $errors, 'lineErrors' => $lineErrors,
    'catalog' => $catalog, 'suppliers' => $suppliers, 'premises' => $premises,
]), 'purchase_order', $id);
