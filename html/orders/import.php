<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/import.php';

// /orders/import: upload and past imports. /orders/import/{n}: one import's preview or result.
$user = require_role('sales');
$pdo = db();
$importId = request_integer('sub_id');
if ($importId === null) {
    log_screen_entered('orders-import');
    render_screen('Import orders', 'orders-import', view('orders/import-page.php', ['imports' => find_order_imports($pdo), 'errors' => [], 'futureStatus' => 'confirmed']));
    exit;
}
$import = find_order_import($pdo, $importId) ?? not_found('That import does not exist.');
$result = null;
$readError = null;
if ($import['status'] === 'previewed') {
    try {
        [$headers, $rows] = orders_import_read_file($import['storage_path'], strtolower(pathinfo($import['file_name'], PATHINFO_EXTENSION)));
        $result = orders_import_validate($pdo, $headers, $rows, $import['mapping'], $import['future_status']);
        $import['headers'] = $headers;
    } catch (RuntimeException $exception) {
        $readError = $exception->getMessage();
    }
}
log_screen_entered('order-import-view', 'order_import', $importId, $import['number']);
render_screen($import['number'], 'order-import-view', view('orders/partials/import-view.php', [
    'import' => $import, 'result' => $result, 'readError' => $readError, 'orders' => $import['status'] === 'imported' ? find_import_orders($pdo, $importId) : [],
    'canPrice' => user_can($user, 'sales'),
]), 'order_import', $importId);
