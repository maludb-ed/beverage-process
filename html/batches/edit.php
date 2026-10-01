<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/batches/queries.php';

// batch-edit (reached through form.php for /batches/{id}/edit): notes and the tax class override.
$user = require_role('production');
$pdo = db();
$id = request_integer('id') ?? not_found('That batch does not exist.');
$batch = find_batch($pdo, $id) ?? not_found('That batch does not exist.');
if ($batch['status'] !== 'active') {   // I5
    http_response_code(409);
    render_screen($batch['number'], 'batch-edit', view('shared/error.php', ['message' => 'Batch ' . $batch['number'] . ' is ' . $batch['status'] . ' and accepts no more changes.']), 'batch', $id);
    exit;
}
$input = ['notes' => $batch['notes'] ?? '', 'tax_class_override' => $batch['tax_class_override'] ?? 'none', 'tax_class_override_reason' => $batch['tax_class_override_reason_code_id']];
log_screen_entered('batch-edit', 'batch', $id, $batch['number']);
render_screen('Edit ' . $batch['number'], 'batch-edit', view('batches/partials/edit.php', [
    'batch' => $batch, 'input' => $input, 'errors' => [], 'reasons' => override_reason_options($pdo), 'canOverride' => user_can($user, 'compliance'),
]), 'batch', $id);
