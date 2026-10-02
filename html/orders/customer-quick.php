<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/customers/queries.php';

// Pattern A: add a customer from the order (or standing order) form and return the customer block with it selected.
// Same rules as the customer form; sales may add customers this way (the full customer form stays with compliance).
require_post();
verify_csrf();
$user = require_role('sales', 'compliance');
$pdo = db();
$prefix = in_array(request_string('prefix', 40), ['order-form', 'standing-order-form'], true) ? request_string('prefix', 40) : 'order-form';
$withDestination = request_string('with_destination', 1) === '1';
$raw = is_array($_POST['new_customer'] ?? null) ? $_POST['new_customer'] : [];
$input = [];
foreach (['name' => 120, 'kind' => 30, 'default_destination' => 30, 'permit_number' => 40, 'contact_name' => 120, 'email' => 200, 'phone' => 40] as $field => $max) {
    $input[$field] = mb_substr(trim((string) ($raw[$field] ?? '')), 0, $max);
}
$errors = [];
if ($input['name'] === '') { $errors['name'] = 'Name is required.'; }
if (!in_options($input['kind'], CUSTOMER_KINDS)) { $errors['kind'] = 'Choose a kind.'; }
if (!in_options($input['default_destination'], CUSTOMER_DESTINATIONS)) { $errors['default_destination'] = 'Choose a usual destination.'; }
if ($input['default_destination'] === 'in_bond_transfer' && $input['permit_number'] === '') {
    $errors['permit_number'] = 'An in-bond consignee needs its TTB permit number.';
}
if ($input['email'] !== '' && filter_var($input['email'], FILTER_VALIDATE_EMAIL) === false) { $errors['email'] = 'Enter a valid email address.'; }
if (!isset($errors['name'])) {
    $same = $pdo->prepare('SELECT name, active FROM app.customers WHERE lower(name) = lower(:n) LIMIT 1');
    $same->execute(['n' => $input['name']]);
    if ($existing = $same->fetch()) {
        $errors['name'] = $existing['active'] ? '"' . $existing['name'] . '" is already a customer; choose it from the list.' : '"' . $existing['name'] . '" exists but is inactive; reactivate it on the Customers screen.';
    }
}

$selected = request_integer('customer_id');
$destination = $withDestination ? request_string('destination_kind', 30) : null;
$newCustomer = $input;
if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $customer = insert_customer($pdo, $input['name'], $input['kind'], $input['default_destination'], $input['permit_number'] ?: null, $input['contact_name'] ?: null,
            $input['email'] ?: null, $input['phone'] ?: null, null, null, true);
        log_activity($pdo, 'customer_created', 'customer', (int) $customer['id'], $customer['name'], null, $customer, ['from' => $prefix], $prefix === 'order-form' ? 'order-add' : 'standing-order-add');
        $pdo->commit();
        $selected = (int) $customer['id'];
        $newCustomer = null;
        if ($withDestination) {
            $destination = order_default_destination($customer);
        }
        hx_trigger('customersChanged');
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $errors['form'] = 'The customer could not be added.';
    }
}
if ($errors !== []) {
    http_response_code(422);
}
echo view('orders/partials/customer-block.php', [
    'prefix' => $prefix, 'customers' => order_customer_options($pdo, $selected), 'selected' => $selected,
    'destination' => $withDestination ? (in_options($destination, ORDER_DESTINATIONS) ? $destination : 'tax_paid_sale') : null,
    'errors' => [], 'newCustomer' => $newCustomer, 'newErrors' => $errors,
]);
