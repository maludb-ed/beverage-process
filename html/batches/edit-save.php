<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/batches/queries.php';

// POST /batches/{id}/save (through save.php): batch_update, and batch_tax_class_override when the override changes.
require_post();
verify_csrf();
$user = require_role('production');
$pdo = db();
$id = request_integer('id') ?? not_found('That batch does not exist.');
$batch = find_batch($pdo, $id) ?? not_found('That batch does not exist.');
if ($batch['status'] !== 'active') {   // I5
    http_response_code(409);
    render_screen($batch['number'], 'batch-edit', view('shared/error.php', ['message' => 'Batch ' . $batch['number'] . ' is ' . $batch['status'] . ' and accepts no more changes.']), 'batch', $id);
    exit;
}
$reasons = override_reason_options($pdo);
$canOverride = user_can($user, 'compliance');
$input = [
    'notes' => request_string('notes', 2000),
    'tax_class_override' => $canOverride ? request_string('tax_class_override', 40) : ($batch['tax_class_override'] ?? 'none'),
    'tax_class_override_reason' => $canOverride ? request_integer('tax_class_override_reason') : $batch['tax_class_override_reason_code_id'],
];
$errors = [];
$override = $input['tax_class_override'] === 'none' || $input['tax_class_override'] === '' ? null : $input['tax_class_override'];
if ($override !== null && !isset(BATCH_TAX_CLASSES[$override])) { $errors['tax_class_override'] = 'Choose a tax class or none.'; }
$changed = $override !== $batch['tax_class_override'];
if ($changed && !$canOverride) { $errors['tax_class_override'] = 'Only compliance or the owner can override the tax class.'; }
if ($override !== null && ($input['tax_class_override_reason'] === null || !isset($reasons[$input['tax_class_override_reason']]))) { $errors['tax_class_override_reason'] = 'Choose the reason for the override.'; }

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $before = ['notes' => $batch['notes'], 'tax_class_override' => $batch['tax_class_override'], 'tax_class_override_reason_code_id' => $batch['tax_class_override_reason_code_id']];
        $saved = update_batch($pdo, $id, $input['notes'] ?: null, $override, $override !== null ? (int) $input['tax_class_override_reason'] : null, (int) $user['id'], $changed);
        log_activity($pdo, $changed ? 'batch_tax_class_overridden' : 'batch_updated', 'batch', $id, $batch['number'], $before, $saved,
            $changed ? ['tax_class_derived' => $batch['tax_class_derived']] : [], 'batch-edit');
        $pdo->commit();
        flash('success', $changed ? 'Tax class of ' . $batch['number'] . ' ' . ($override !== null ? 'overridden to ' . humanize($override) : 'override removed') . '.' : $batch['number'] . ' saved.');
        hx_trigger('batchesChanged');
        hx_location('/batches/' . $id);
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('batch update failed: ' . $exception->getMessage());
        $errors['form'] = db_error_message($exception) ?? 'The batch could not be saved.';
    }
}
http_response_code(422);
render_screen('Edit ' . $batch['number'], 'batch-edit', view('batches/partials/edit.php', ['batch' => $batch, 'input' => $input, 'errors' => $errors, 'reasons' => $reasons, 'canOverride' => $canOverride]), 'batch', $id);
