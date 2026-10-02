<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/import.php';

require_post();
verify_csrf();
$user = require_role('sales');
$pdo = db();
$importId = request_integer('sub_id') ?? not_found('That import does not exist.');
$import = find_order_import($pdo, $importId) ?? not_found('That import does not exist.');
try {
    $pdo->beginTransaction();
    $deleted = orders_import_undo($pdo, $importId, (int) $user['id']);
    log_activity($pdo, 'orders_import_undone', 'order_import', $importId, $import['number'], ['status' => 'imported', 'orders' => (int) $import['orders_created']],
        ['status' => 'undone', 'orders_deleted' => $deleted], ['file' => $import['file_name']], 'order-import-view');
    $pdo->commit();
    flash('success', $import['number'] . ' undone: ' . $deleted . ' orders deleted. Customers it created are kept.');
    hx_trigger('ordersChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($exception->getMessage());
    flash('error', !$exception instanceof PDOException ? $exception->getMessage() : 'The import could not be undone.');
}
hx_location('/orders/import/' . $importId);
