<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/import.php';

// Set a preview aside without importing it.
require_post();
verify_csrf();
$user = require_role('sales');
$pdo = db();
$importId = request_integer('sub_id') ?? not_found('That import does not exist.');
$import = find_order_import($pdo, $importId) ?? not_found('That import does not exist.');
try {
    $pdo->beginTransaction();
    if (!discard_order_import($pdo, $importId)) {
        throw new RuntimeException('Only a preview can be discarded.');
    }
    log_activity($pdo, 'orders_import_discarded', 'order_import', $importId, $import['number'], ['status' => 'previewed'], ['status' => 'abandoned'], ['file' => $import['file_name']], 'order-import-view');
    $pdo->commit();
    flash('success', $import['number'] . ' discarded; nothing was imported.');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($exception->getMessage());
    flash('error', !$exception instanceof PDOException ? $exception->getMessage() : 'The import could not be discarded.');
}
hx_location('/orders/import');
