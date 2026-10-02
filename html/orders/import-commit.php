<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/import.php';

// Create the previewed orders in one transaction, with one activity event for the whole import.
require_post();
verify_csrf();
$user = require_role('sales');
$pdo = db();
$importId = request_integer('sub_id') ?? not_found('That import does not exist.');
try {
    $pdo->beginTransaction();
    $locked = $pdo->prepare('SELECT status FROM app.order_imports WHERE id = :id FOR UPDATE');
    $locked->execute(['id' => $importId]);
    $import = find_order_import($pdo, $importId) ?? not_found('That import does not exist.');
    if ($import['status'] !== 'previewed') {
        throw new RuntimeException($import['number'] . ' is already ' . $import['status'] . '.');
    }
    [$headers, $rows] = orders_import_read_file($import['storage_path'], strtolower(pathinfo($import['file_name'], PATHINFO_EXTENSION)));
    $result = orders_import_validate($pdo, $headers, $rows, $import['mapping'], $import['future_status']);
    if ($result['counts']['errors'] > 0 && !post_bool('skip_errors')) {
        throw new RuntimeException($result['counts']['errors'] . ' rows have errors. Fix the file and upload it again, or choose to skip those rows.');
    }
    if ($result['orders'] === []) {
        throw new RuntimeException('There is nothing to import.');
    }
    $done = orders_import_commit($pdo, $import, $result, post_bool('create_customers'), (int) $user['id']);
    if ($done['orders'] === 0) {
        throw new RuntimeException('No order could be imported; allow new customers to be created or fix the file.');
    }
    log_activity($pdo, 'orders_imported', 'order_import', $importId, $import['number'], ['status' => 'previewed'],
        ['status' => 'imported', 'orders' => $done['orders'], 'rows_imported' => $done['imported_rows'], 'rows_skipped' => $result['counts']['skipped'],
         'rows_failed' => $done['failed'], 'customers_created' => $done['customers']],
        ['file' => $import['file_name'], 'history' => $result['counts']['history'], 'confirmed' => $result['counts']['confirmed'], 'draft' => $result['counts']['draft']], 'order-import-view');
    $pdo->commit();
    flash('success', $import['number'] . ': ' . $done['orders'] . ' orders imported' . ($done['customers'] ? ', ' . $done['customers'] . ' customers created' : '')
        . ($done['failed'] ? ', ' . $done['failed'] . ' rows not imported' : '') . '.');
    hx_trigger('ordersChanged');
} catch (RuntimeException | PDOException | JsonException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($exception->getMessage());
    flash('error', $exception instanceof PDOException ? (db_error_message($exception) ?? 'The import failed; nothing was imported.') : $exception->getMessage());
}
hx_location('/orders/import/' . $importId);
