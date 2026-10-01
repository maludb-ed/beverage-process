<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/customers/queries.php';

require_post();
verify_csrf();
$user = require_role('compliance');
$pdo = db();

$id = request_integer('id');
$input = [
    'id' => $id,
    'name' => request_string('name', 120),
    'kind' => request_string('kind', 30),
    'default_destination' => request_string('default_destination', 30),
    'permit_number' => request_string('permit_number', 40),
    'contact_name' => request_string('contact_name', 120),
    'email' => request_string('email', 200),
    'phone' => request_string('phone', 40),
    'address' => request_string('address', 1000),
    'notes' => request_string('notes', 2000),
    'active' => post_bool('active'),
];
$errors = [];
if ($input['name'] === '') { $errors['name'] = 'Name is required.'; }
if (!in_options($input['kind'], CUSTOMER_KINDS)) { $errors['kind'] = 'Choose a kind.'; }
if (!in_options($input['default_destination'], CUSTOMER_DESTINATIONS)) { $errors['default_destination'] = 'Choose a default destination.'; }
if ($input['default_destination'] === 'in_bond_transfer' && $input['permit_number'] === '') {
    $errors['permit_number'] = 'An in-bond consignee needs its TTB permit number.';
}
if ($input['email'] !== '' && filter_var($input['email'], FILTER_VALIDATE_EMAIL) === false) { $errors['email'] = 'Enter a valid email address.'; }

$before = $id !== null ? (find_customer($pdo, $id) ?? not_found('That customer does not exist.')) : null;

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $args = [$input['name'], $input['kind'], $input['default_destination'], $input['permit_number'] ?: null, $input['contact_name'] ?: null,
            $input['email'] ?: null, $input['phone'] ?: null, $input['address'] ?: null, $input['notes'] ?: null, $input['active']];
        $customer = $id === null ? insert_customer($pdo, ...$args) : update_customer($pdo, $id, ...$args);
        log_activity($pdo, $id === null ? 'customer_created' : 'customer_updated', 'customer', (int) $customer['id'], $customer['name'],
            $before === null ? null : array_intersect_key($before, $customer), $customer, [], $id === null ? 'customer-add' : 'customer-edit');
        $pdo->commit();
        flash('success', 'Customer "' . $customer['name'] . '" saved.');
        hx_trigger('customersChanged');
        hx_location('/customers/' . (int) $customer['id']);
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The customer could not be saved.') : $exception->getMessage();
    }
}
http_response_code(422);
render_screen($id ? 'Edit ' . ($before['name'] ?? 'Customer') : 'Add Customer', $id ? 'customer-edit' : 'customer-add', view('customers/partials/form.php', ['customer' => $input, 'errors' => $errors]), 'customer', $id);
