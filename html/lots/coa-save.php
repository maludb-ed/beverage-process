<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/lots/queries.php';

require_post();
verify_csrf();
$user = require_role('quality');
$pdo = db();
$id = request_integer('id') ?? not_found('That lot does not exist.');
$lot = find_lot($pdo, $id) ?? not_found('That lot does not exist.');

$input = ['issued_on' => request_string('issued_on', 10), 'issuer' => request_string('issuer', 120), 'values' => []];
$errors = [];
$issuedOn = post_date('issued_on');
if ($issuedOn === false) { $errors['issued_on'] = 'Use a valid date.'; }
foreach ((is_array($_POST['values'] ?? null) ? $_POST['values'] : []) as $row) {
    $key = (string) ($row['key'] ?? '');
    $value = trim((string) ($row['value'] ?? ''));
    if ($key === '' && $value === '') {
        continue;
    }
    if (!isset(LOT_ATTRIBUTE_KEYS[$key]) || $value === '') {
        $errors['values'] = 'Each value needs a name and a value.';
    }
    $input['values'][] = ['key' => $key, 'value' => mb_substr($value, 0, 80), 'unit' => mb_substr(trim((string) ($row['unit'] ?? '')), 0, 20)];
}
$file = $_FILES['file'] ?? null;
$hasFile = $file !== null && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
$allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
$mime = null;
if ($hasFile) {
    if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 10 * 1024 * 1024) {
        $errors['file'] = 'The file did not upload; it must be 10 MB or smaller.';
    } else {
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']) ?: '';
        if (!isset($allowed[$mime])) { $errors['file'] = 'Upload a PDF, JPG or PNG.'; }
    }
}
if (!$hasFile && $input['values'] === []) { $errors['form'] = 'Attach the document or enter at least one value.'; }

if ($errors === []) {
    $storedPath = null;
    try {
        $pdo->beginTransaction();
        $attachmentId = null;
        if ($hasFile) {
            $client = preg_replace('/[^a-z0-9-]/', '', (string) (client_settings()['subdomain'] ?? 'default')) ?: 'default';
            $dir = dirname(__DIR__, 2) . '/storage/' . $client . '/coa';
            if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
                throw new RuntimeException('Storage is not writable.');
            }
            $storedPath = $dir . '/' . bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
            if (!move_uploaded_file($file['tmp_name'], $storedPath)) {
                throw new RuntimeException('The file could not be stored.');
            }
            $original = mb_substr(preg_replace('/[^\w.\- ]+/u', '_', basename((string) $file['name'])), 0, 120);
            $attachment = insert_attachment($pdo, 'lot', $id, 'coa', $original, $mime, $storedPath, (int) $file['size'], (int) $user['id']);
            $attachmentId = (int) $attachment['id'];
        }
        $certificate = insert_certificate($pdo, $id, $attachmentId, $issuedOn, $input['issuer'] ?: null, $input['values'], (int) $user['id']);
        foreach ($input['values'] as $value) {
            $isNumber = is_numeric(str_replace(',', '', $value['value']));
            set_lot_attribute($pdo, $id, $value['key'], $isNumber ? (float) str_replace(',', '', $value['value']) : null, $isNumber ? null : $value['value'], $value['unit'] ?: null, 'coa', (int) $user['id']);
        }
        log_activity($pdo, 'coa_recorded', 'lot', $id, $lot['lot_number'], null, ['certificate_id' => $certificate['id'], 'values' => $input['values'], 'file' => $attachmentId !== null], [], 'lot-coa-add');
        $pdo->commit();
        flash('success', 'Certificate recorded on ' . $lot['lot_number'] . '.');
        hx_trigger('lotsChanged');
        hx_location('/lots/' . $id . '?tab=certificates');
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        if ($storedPath !== null && is_file($storedPath)) { unlink($storedPath); }
        error_log($exception->getMessage());
        $errors['form'] = 'The certificate could not be saved.';
    }
}
http_response_code(422);
render_screen('Certificate for ' . $lot['lot_number'], 'lot-coa-add', view('lots/partials/coa-form.php', ['lot' => $lot, 'input' => $input, 'errors' => $errors]), 'lot', $id);
