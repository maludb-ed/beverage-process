<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/releases/queries.php';

require_post();
verify_csrf();
$user = require_role('quality');
$pdo = db();
$id = request_integer('id') ?? not_found('That batch does not exist.');
$context = find_batch_release_context($pdo, $id) ?: not_found('That batch does not exist.');
$batch = $context['batch'];
$reasons = override_reason_options($pdo);
$input = [
    'to_status' => request_string('to_status', 20), 'basis' => request_string('basis', 20), 'is_override' => post_bool('is_override'),
    'reason_code_id' => request_integer('reason_code_id'), 'note' => request_string('note', 2000),
];
// Releasing against a failed reading is always an override, whatever the checkbox says.
if ($input['basis'] === 'override' || ($input['to_status'] === 'released' && $context['failing_count'] > 0)) { $input['is_override'] = true; }
$errors = [];
if ($batch['status'] !== 'active') { $errors['form'] = 'Only active batches can be released.'; }
if (!in_options($input['to_status'], BATCH_RELEASE_STATUSES)) { $errors['to_status'] = 'Choose released, hold or rejected.'; }
elseif (($context['last_decision']['to_status'] ?? null) === $input['to_status'] && $context['last_decision']['decided_at'] >= $batch['entered_at']) {
    $errors['to_status'] = 'This batch is already ' . $input['to_status'] . ' since its current stage began. Choose a different decision.';
}
if (!in_options($input['basis'], BATCH_RELEASE_BASES)) { $errors['basis'] = 'Choose the basis for the decision.'; }
if ($input['is_override']) {
    if ($input['reason_code_id'] === null || !isset($reasons[$input['reason_code_id']])) { $errors['reason_code_id'] = 'An override needs a reason code.'; }
    if ($input['note'] === '') { $errors['note'] = 'An override needs a note explaining it.'; }
} else {
    $input['reason_code_id'] = null;
}

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $fromStatus = ($context['last_decision']['to_status'] ?? null) === 'released' ? 'released' : 'pending';
        $decision = insert_batch_release_decision($pdo, $id, $fromStatus, $input['to_status'], $input['basis'], $input['is_override'], $input['reason_code_id'], $input['note'] ?: null, (int) $user['id']);
        log_activity($pdo, 'batch_release_decided', 'batch', $id, $batch['number'], ['status' => $fromStatus],
            ['status' => $input['to_status'], 'basis' => $input['basis'], 'is_override' => $input['is_override'], 'failing_readings' => $context['failing_count']],
            ['decision_id' => $decision['id']], 'batch-release');
        $pdo->commit();
        flash('success', 'Batch ' . $batch['number'] . ' decision recorded: ' . strtolower(BATCH_RELEASE_STATUSES[$input['to_status']]) . '.');
        hx_trigger('releasesChanged');
        hx_location('/releases/');
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('batch release failed: ' . $exception->getMessage());
        $errors['form'] = db_error_message($exception) ?? 'The decision could not be saved.';
    }
}
http_response_code(422);
render_screen('Release ' . $batch['number'], 'batch-release', view('releases/partials/batch-release-form.php', $context + [
    'input' => $input, 'errors' => $errors, 'reasons' => $reasons, 'defaultReasonId' => releases_default_reason_id($pdo),
]), 'batch', $id);
