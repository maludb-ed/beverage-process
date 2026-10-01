<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/approvals/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/products/queries.php';

require_post();
verify_csrf();
$user = require_role('compliance');
$pdo = db();

$id = request_integer('id');
$products = products_options($pdo, true);
$input = [
    'id' => $id,
    'product_id' => request_integer('product_id'),
    'kind' => request_string('kind', 10),
    'packaging_configuration_id' => request_integer('packaging_configuration_id'),
    'reference_no' => request_string('reference_no', 80),
    'status' => request_string('status', 20),
    'approved_on' => post_date('approved_on'),
    'expires_on' => post_date('expires_on'),
    'notes' => request_string('notes', 2000),
];
$packages = $input['product_id'] !== null ? approval_package_options($pdo, $input['product_id']) : [];
$errors = [];
if ($input['product_id'] === null || !isset($products[$input['product_id']])) { $errors['product'] = 'Choose a product.'; }
if (!in_options($input['kind'], APPROVAL_KINDS)) { $errors['kind'] = 'Choose formula or label.'; }
if (!in_options($input['status'], APPROVAL_STATUSES)) { $errors['status'] = 'Choose a status.'; }
if ($input['packaging_configuration_id'] !== null) {
    if ($input['kind'] !== 'label') { $errors['packaging_config'] = 'Only a label approval names a package.'; }
    elseif (!isset($packages[$input['packaging_configuration_id']])) { $errors['packaging_config'] = 'Choose a package of this product.'; }
}
if ($input['approved_on'] === false) { $errors['approved_on'] = 'Use a valid date.'; }
elseif ($input['approved_on'] === null && $input['status'] === 'approved') { $errors['approved_on'] = 'Enter the approval date.'; }
if ($input['expires_on'] === false) { $errors['expires_on'] = 'Use a valid date.'; }

$file = $_FILES['attachment'] ?? null;
$hasFile = $file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
$allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
$mime = null;
if ($hasFile) {
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 10 * 1024 * 1024) {
        $errors['attachment'] = 'The file did not upload; it must be 10 MB or smaller.';
    } else {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
        if (!isset($allowed[$mime])) { $errors['attachment'] = 'Upload a PDF, JPG or PNG.'; }
    }
}

$before = $id !== null ? (find_approval($pdo, $id) ?? not_found('That approval does not exist.')) : null;
if ($before !== null) {
    $input['attachment_name'] = $before['attachment_name'];
    $input['product_name'] = $before['product_name'];
}

if ($errors === []) {
    $storedPath = null;
    try {
        $pdo->beginTransaction();
        $args = [(int) $input['product_id'], $input['kind'], $input['kind'] === 'label' ? $input['packaging_configuration_id'] : null, $input['reference_no'] ?: null,
            $input['status'], $input['approved_on'], $input['expires_on'], null, $input['notes'] ?: null];
        $approval = $id === null ? insert_approval($pdo, ...$args) : update_approval($pdo, $id, ...$args);
        if ($hasFile) {
            $client = preg_replace('/[^a-z0-9-]/', '', (string) (client_settings()['subdomain'] ?? 'default')) ?: 'default';
            $dir = dirname(__DIR__, 2) . '/storage/' . $client . '/approvals';
            if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
                throw new RuntimeException('Storage is not writable.');
            }
            $storedPath = $dir . '/' . bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
            if (!move_uploaded_file($file['tmp_name'], $storedPath)) {
                throw new RuntimeException('The file could not be stored.');
            }
            $original = mb_substr(preg_replace('/[^\w.\- ]+/u', '_', basename((string) $file['name'])), 0, 120);
            $approval['attachment_id'] = insert_approval_attachment($pdo, (int) $approval['id'], $input['kind'] === 'label' ? 'cola' : 'formula', $original, $mime, $storedPath, (int) $file['size'], (int) $user['id']);
        }
        $label = $products[$approval['product_id']] . ' ' . $approval['kind'];
        log_activity($pdo, $id === null ? 'approval_recorded' : 'approval_updated', 'product_approval', (int) $approval['id'], $label,
            $before === null ? null : array_intersect_key($before, $approval), $approval, [], $id === null ? 'approval-add' : 'approval-edit');
        $pdo->commit();
        flash('success', 'Approval saved for ' . $products[$approval['product_id']] . '.');
        hx_trigger('approvalsChanged');
        hx_location('/approvals/');
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($storedPath !== null && is_file($storedPath)) { unlink($storedPath); }
        error_log($exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The approval could not be saved.') : $exception->getMessage();
    }
}
http_response_code(422);
render_screen($id ? 'Edit approval' : 'Record approval', $id ? 'approval-edit' : 'approval-add', view('approvals/partials/form.php', [
    'approval' => $input, 'errors' => $errors, 'products' => $products, 'packages' => $packages,
]), 'product_approval', $id);
