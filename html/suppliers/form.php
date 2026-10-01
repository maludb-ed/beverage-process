<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/suppliers/queries.php';

$user = require_role('receiving');
$id = request_integer('id');
if ($id !== null) {
    $supplier = find_supplier(db(), $id) ?? not_found('That supplier does not exist.');
    $screen = 'supplier-edit';
} else {
    $kind = request_string('kind');
    $supplier = ['name' => request_string('name', 160), 'kind' => in_options($kind, SUPPLIER_KINDS) ? $kind : 'vendor', 'active' => true];
    $screen = 'supplier-add';
}
log_screen_entered($screen, 'supplier', $id, $supplier['name'] ?? null);
render_screen($id ? 'Edit Supplier' : 'Add Supplier', $screen, view('suppliers/partials/form.php', ['supplier' => $supplier, 'errors' => []]), 'supplier', $id);
