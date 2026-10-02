<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/import.php';

// Change the column mapping or how upcoming orders import, then show the preview again.
require_post();
verify_csrf();
$user = require_role('sales');
$pdo = db();
$importId = request_integer('sub_id') ?? not_found('That import does not exist.');
$import = find_order_import($pdo, $importId) ?? not_found('That import does not exist.');
if ($import['status'] !== 'previewed') {
    hx_location('/orders/import/' . $importId);
}
$posted = is_array($_POST['mapping'] ?? null) ? $_POST['mapping'] : [];
$mapping = [];
foreach (array_keys(ORDER_IMPORT_FIELDS) as $field) {
    $header = trim((string) ($posted[$field] ?? ''));
    if ($header !== '') {
        $mapping[$field] = mb_substr($header, 0, 200);
    }
}
$futureStatus = request_string('future_status', 10) === 'draft' ? 'draft' : 'confirmed';
try {
    $pdo->beginTransaction();
    update_order_import_mapping($pdo, $importId, $mapping, $futureStatus);
    log_activity($pdo, 'orders_import_previewed', 'order_import', $importId, $import['number'], ['mapping' => $import['mapping'], 'future_status' => $import['future_status']],
        ['mapping' => $mapping, 'future_status' => $futureStatus], [], 'order-import-view');
    $pdo->commit();
} catch (PDOException | JsonException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($exception->getMessage());
    flash('error', 'The mapping could not be saved.');
}
hx_location('/orders/import/' . $importId);
