<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/customers/queries.php';

require_post();
verify_csrf();
// Compliance, and sales (who can add customers from the order form), may delete a customer with no history.
$user = require_role('compliance', 'sales');
$pdo = db();
$id = request_integer('id') ?? not_found('That customer does not exist.');
$customer = find_customer($pdo, $id) ?? not_found('That customer does not exist.');
try {
    $pdo->beginTransaction();
    $history = customer_history($pdo, $id);
    $deleted = delete_customer($pdo, $id);
    log_activity($pdo, $deleted ? 'customer_deleted' : 'customer_updated', 'customer', $id, $customer['name'], $customer, $deleted ? null : ['active' => false],
        ['reason' => $deleted ? 'deleted, no history' : 'has history (' . history_summary($history) . '), deactivated'], 'customer-view');
    $pdo->commit();
    flash('success', $deleted ? 'Customer "' . $customer['name'] . '" deleted.' : '"' . $customer['name'] . '" has history (' . history_summary($history) . '), so it was deactivated instead of deleted.');
    hx_trigger('customersChanged');
    hx_location($deleted ? '/customers/' : '/customers/' . $id);
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($exception->getMessage());
    flash('error', 'The customer could not be deleted.');
}
hx_location('/customers/' . $id);
