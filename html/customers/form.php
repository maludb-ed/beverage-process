<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/customers/queries.php';

$user = require_role('compliance');
$id = request_integer('id');
if ($id !== null) {
    $customer = find_customer(db(), $id) ?? not_found('That customer does not exist.');
    $screen = 'customer-edit';
} else {
    // Prefill: ?name=, ?kind=
    $kind = request_string('kind', 30);
    $customer = ['name' => request_string('name', 120), 'kind' => in_options($kind, CUSTOMER_KINDS) ? $kind : 'distributor', 'default_destination' => 'tax_paid_sale', 'active' => true];
    $screen = 'customer-add';
}
log_screen_entered($screen, 'customer', $id, $customer['name'] ?: null);
render_screen($id ? 'Edit ' . $customer['name'] : 'Add Customer', $screen, view('customers/partials/form.php', ['customer' => $customer, 'errors' => []]), 'customer', $id);
