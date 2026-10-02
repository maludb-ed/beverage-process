<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/validation.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';

require_post();
verify_csrf();
$user = require_role('sales');
$pdo = db();

$id = request_integer('id');
$before = null;
if ($id !== null) {
    $before = find_order($pdo, $id) ?? not_found('That order does not exist.');
}
$premises = premises_options($pdo);
$customers = order_customer_options($pdo, $before ? (int) $before['customer_id'] : null);
$order = [
    'id' => $id,
    'number' => $before['number'] ?? null,
    'status' => $before['status'] ?? 'draft',
    'customer_id' => request_integer('customer_id'),
    'premises_id' => request_integer('premises_id'),
    'destination_kind' => request_string('destination_kind', 30),
    'ordered_on' => post_date('ordered_on'),
    'requested_on' => post_date('requested_on'),
    'customer_reference' => request_string('customer_reference', 60),
    'fulfilled_outside' => post_bool('fulfilled_outside'),
    'notes' => request_string('notes', 2000),
];
$errors = [];
if ($order['customer_id'] === null || !isset($customers[$order['customer_id']])) { $errors['customer_id'] = 'Choose a customer.'; }
if ($order['premises_id'] === null || !isset($premises[$order['premises_id']])) { $errors['premises_id'] = 'Choose a premises.'; }
if (!in_options($order['destination_kind'], ORDER_DESTINATIONS)) { $errors['destination_kind'] = 'Choose where the order goes.'; }
if ($order['ordered_on'] === false || $order['ordered_on'] === null) { $errors['ordered_on'] = 'Enter the date the order was placed.'; }
if ($order['requested_on'] === false || $order['requested_on'] === null) { $errors['requested_on'] = 'Enter the date the customer wants it.'; }
if (!isset($errors['ordered_on']) && !isset($errors['requested_on']) && $order['requested_on'] < $order['ordered_on']) {
    $errors['requested_on'] = 'The due date cannot be before the order date.';
}
if ($order['fulfilled_outside'] && $order['status'] !== 'draft') {
    $errors['fulfilled_outside'] = 'Only a new or draft order can be recorded as fulfilled outside the system.';
}
if ($before !== null && !in_array($before['status'], ['draft', 'confirmed'], true)) {
    $errors['form'] = 'Only draft or confirmed orders can be edited.';
}

$keepFormats = $id !== null ? array_column(find_order_lines($pdo, $id), 'packaging_configuration_id') : [];
$catalog = order_format_catalog($pdo, $keepFormats);
$canPrice = user_can($user, 'sales');
[$lines, $lineErrors] = validate_order_lines(is_array($_POST['lines'] ?? null) ? $_POST['lines'] : [], $catalog, $canPrice);
if ($lines === []) { $errors['lines'] = 'Add at least one line.'; }
if ($lineErrors !== []) { $errors['line_rows'] = 'Fix the highlighted lines.'; }

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $saved = $id === null ? insert_order($pdo, $order, (int) $user['id']) : update_order($pdo, $id, $order, (int) $user['id']);
        save_order_lines($pdo, (int) $saved['id'], $lines);
        log_activity($pdo, $id === null ? 'order_created' : 'order_updated', 'sales_order', (int) $saved['id'], $saved['number'],
            $before === null ? null : array_intersect_key($before, $saved), $saved + ['lines' => array_values($lines)],
            ['customer' => $customers[$saved['customer_id']]['name'] ?? null], $id === null ? 'order-add' : 'order-edit');
        $pdo->commit();
        flash('success', 'Order ' . $saved['number'] . ($saved['fulfilled_outside'] ? ' recorded as history.' : ' saved.'));
        hx_trigger('ordersChanged');
        hx_location('/orders/' . $saved['id']);
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        if ($exception instanceof PDOException && is_unique_violation($exception)) {
            $errors['customer_reference'] = 'This customer already has an order with that reference.';
        } else {
            $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The order could not be saved.') : $exception->getMessage();
        }
    }
}
http_response_code(422);
render_screen($id ? 'Edit ' . $order['number'] : 'New Order', $id ? 'order-edit' : 'order-add', view('orders/partials/form.php', [
    'order' => $order, 'lines' => $lines === [] ? ['n1' => []] : $lines, 'errors' => $errors, 'lineErrors' => $lineErrors,
    'catalog' => $catalog, 'customers' => $customers, 'premises' => $premises, 'canPrice' => $canPrice,
]), 'sales_order', $id);
