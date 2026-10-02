<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/purchase-orders/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/suppliers/queries.php';

// Pattern A: add a supplier from the purchase order form and return the supplier block with it selected.
// Same rules and role as the supplier form, plus a check for an existing supplier with the same name.
require_post();
verify_csrf();
$user = require_role('receiving');
$pdo = db();
$raw = is_array($_POST['new_supplier'] ?? null) ? $_POST['new_supplier'] : [];
$input = [];
foreach (['name' => 160, 'kind' => 20, 'contact_name' => 120, 'email' => 200, 'phone' => 40] as $field => $max) {
    $input[$field] = mb_substr(trim((string) ($raw[$field] ?? '')), 0, $max);
}
$errors = [];
if ($input['name'] === '') { $errors['name'] = 'Name is required.'; }
if (!in_options($input['kind'], SUPPLIER_KINDS)) { $errors['kind'] = 'Choose a supplier kind.'; }
if ($input['email'] !== '' && filter_var($input['email'], FILTER_VALIDATE_EMAIL) === false) { $errors['email'] = 'Enter a valid email address.'; }
if (!isset($errors['name'])) {
    $same = $pdo->prepare('SELECT name, active FROM app.suppliers WHERE lower(name) = lower(:n) LIMIT 1');
    $same->execute(['n' => $input['name']]);
    if ($existing = $same->fetch()) {
        $errors['name'] = $existing['active'] ? '"' . $existing['name'] . '" is already a supplier; choose it from the list.' : '"' . $existing['name'] . '" exists but is inactive; reactivate it on the Suppliers screen.';
    }
}
$selected = request_integer('supplier_id');
$newSupplier = $input;
if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $supplier = insert_supplier($pdo, $input['name'], $input['kind'], $input['contact_name'] ?: null, $input['email'] ?: null, $input['phone'] ?: null, null, null, true);
        log_activity($pdo, 'supplier_created', 'supplier', (int) $supplier['id'], $supplier['name'], null, $supplier, ['from' => 'purchase order form'], 'purchase-order-add');
        $pdo->commit();
        $selected = (int) $supplier['id'];
        $newSupplier = null;
        hx_trigger('suppliersChanged');
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $errors['form'] = 'The supplier could not be added.';
    }
}
if ($errors !== []) {
    http_response_code(422);
}
echo view('purchase-orders/partials/supplier-block.php', [
    'suppliers' => purchasing_supplier_options($pdo), 'selected' => $selected, 'errors' => [], 'newSupplier' => $newSupplier, 'newErrors' => $errors,
]);
