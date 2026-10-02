<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/suppliers/queries.php';

// Delete a supplier with no purchase orders, receipts or lots; one with history is deactivated instead.
require_post();
verify_csrf();
$user = require_role('receiving');
$pdo = db();
$id = request_integer('id') ?? not_found('That supplier does not exist.');
$supplier = find_supplier($pdo, $id) ?? not_found('That supplier does not exist.');
try {
    $pdo->beginTransaction();
    $history = supplier_history($pdo, $id);
    $deleted = delete_supplier($pdo, $id);
    log_activity($pdo, $deleted ? 'supplier_deleted' : 'supplier_updated', 'supplier', $id, $supplier['name'], $supplier, $deleted ? null : ['active' => false],
        ['reason' => $deleted ? 'deleted, no history' : 'has history (' . history_summary($history) . '), deactivated'], 'supplier-view');
    $pdo->commit();
    flash('success', $deleted ? 'Supplier "' . $supplier['name'] . '" deleted.' : '"' . $supplier['name'] . '" has history (' . history_summary($history) . '), so it was deactivated instead of deleted.');
    hx_trigger('suppliersChanged');
    hx_location($deleted ? '/suppliers/' : '/suppliers/' . $id);
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($exception->getMessage());
    flash('error', 'The supplier could not be deleted.');
}
hx_location('/suppliers/' . $id);
