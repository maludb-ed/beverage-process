<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/import.php';
require_once dirname(__DIR__, 2) . '/app/features/lots/queries.php';

// Upload a spreadsheet: store it, guess the column mapping, and open the preview. Nothing is imported yet.
require_post();
verify_csrf();
$user = require_role('sales');
$pdo = db();
$futureStatus = request_string('future_status', 10) === 'draft' ? 'draft' : 'confirmed';
$errors = [];
$file = $_FILES['file'] ?? null;
$extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    $errors['file'] = 'Choose a CSV or Excel file.';
} elseif ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > ORDER_IMPORT_MAX_BYTES) {
    $errors['file'] = 'The file did not upload; it must be 5 MB or smaller.';
} elseif (!isset(ORDER_IMPORT_EXTENSIONS[$extension])) {
    $errors['file'] = 'Upload a .csv, .xlsx or .xls file.';
}
$headers = [];
$rows = [];
if ($errors === []) {
    try {
        [$headers, $rows] = orders_import_read_file($file['tmp_name'], $extension);
        if ($rows === []) { $errors['file'] = 'The file has headers but no rows.'; }
    } catch (RuntimeException $exception) {
        $errors['file'] = $exception->getMessage();
    }
}
if ($errors === []) {
    $storedPath = null;
    try {
        $pdo->beginTransaction();
        $client = preg_replace('/[^a-z0-9-]/', '', (string) (client_settings()['subdomain'] ?? 'default')) ?: 'default';
        $dir = dirname(__DIR__, 2) . '/storage/' . $client . '/order-imports';
        if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
            throw new RuntimeException('Storage is not writable.');
        }
        $storedPath = $dir . '/' . bin2hex(random_bytes(16)) . '.' . $extension;
        if (!move_uploaded_file($file['tmp_name'], $storedPath)) {
            throw new RuntimeException('The file could not be stored.');
        }
        $original = mb_substr(preg_replace('/[^\w.\- ]+/u', '_', basename((string) $file['name'])), 0, 120);
        // The attachment points at its import once the import row exists.
        $attachment = insert_attachment($pdo, 'order_import', 0, 'spreadsheet', $original, ORDER_IMPORT_EXTENSIONS[$extension], $storedPath, (int) $file['size'], (int) $user['id']);
        $mapping = orders_import_guess_mapping($headers, orders_import_previous_mapping($pdo));
        $import = insert_order_import($pdo, (int) $attachment['id'], $mapping, $futureStatus, count($rows), (int) $user['id']);
        $pdo->prepare('UPDATE app.attachments SET entity_id = :i WHERE id = :a')->execute(['i' => $import['id'], 'a' => $attachment['id']]);
        log_activity($pdo, 'orders_import_previewed', 'order_import', (int) $import['id'], $import['number'], null,
            ['file' => $original, 'rows' => count($rows), 'mapping' => $mapping, 'future_status' => $futureStatus], [], 'orders-import');
        $pdo->commit();
        hx_location('/orders/import/' . $import['id']);
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($storedPath !== null && is_file($storedPath)) { unlink($storedPath); }
        error_log($exception->getMessage());
        $errors['file'] = $exception instanceof PDOException ? 'The file could not be saved.' : $exception->getMessage();
    }
}
http_response_code(422);
render_screen('Import orders', 'orders-import', view('orders/import-page.php', ['imports' => find_order_imports($pdo), 'errors' => $errors, 'futureStatus' => $futureStatus]));
