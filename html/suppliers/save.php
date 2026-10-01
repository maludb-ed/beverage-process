<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/suppliers/queries.php';

require_post();
verify_csrf();
$user = require_role('receiving');

$id = request_integer('id');
$input = [
    'id' => $id,
    'name' => request_string('name', 160),
    'kind' => request_string('kind', 20),
    'contact_name' => request_string('contact_name', 120),
    'email' => request_string('email', 200),
    'phone' => request_string('phone', 40),
    'address' => request_string('address', 500),
    'notes' => request_string('notes', 2000),
    'active' => post_bool('active'),
];
$errors = [];
if ($input['name'] === '') { $errors['name'] = 'Name is required.'; }
if (!in_options($input['kind'], SUPPLIER_KINDS)) { $errors['kind'] = 'Choose a supplier kind.'; }
if ($input['email'] !== '' && filter_var($input['email'], FILTER_VALIDATE_EMAIL) === false) { $errors['email'] = 'Enter a valid email address.'; }

$pdo = db();
$before = $id !== null ? (find_supplier($pdo, $id) ?? not_found('That supplier does not exist.')) : null;

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $args = [$input['name'], $input['kind'], $input['contact_name'] ?: null, $input['email'] ?: null, $input['phone'] ?: null,
            $input['address'] ?: null, $input['notes'] ?: null, $input['active']];
        $supplier = $id === null ? insert_supplier($pdo, ...$args) : update_supplier($pdo, $id, ...$args);
        log_activity($pdo, $id === null ? 'supplier_created' : 'supplier_updated', 'supplier', (int) $supplier['id'], $supplier['name'],
            $before, $supplier, [], $id === null ? 'supplier-add' : 'supplier-edit');
        $pdo->commit();
        flash('success', 'Supplier "' . $supplier['name'] . '" saved.');
        hx_trigger('supplierChanged');
        hx_location('/suppliers/' . $supplier['id']);
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $errors['form'] = db_error_message($exception) ?? 'The supplier could not be saved.';
    }
}
http_response_code(422);
render_screen($id ? 'Edit Supplier' : 'Add Supplier', $id ? 'supplier-edit' : 'supplier-add', view('suppliers/partials/form.php', ['supplier' => $input, 'errors' => $errors]), 'supplier', $id);
