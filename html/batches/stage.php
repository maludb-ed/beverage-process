<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/batches/queries.php';

$user = require_role('production');
$pdo = db();
$id = request_integer('id') ?? not_found('That batch does not exist.');
$batch = find_batch($pdo, $id) ?? not_found('That batch does not exist.');
if ($batch['status'] !== 'active') {   // I5
    http_response_code(409);
    render_screen($batch['number'], 'batch-stage-move', view('shared/error.php', ['message' => 'Batch ' . $batch['number'] . ' is ' . $batch['status'] . ' and accepts no more events.']), 'batch', $id);
    exit;
}
$options = batches_next_stage_options($pdo, $batch);
// Prefill (manifest): stage by code or name.
$wanted = mb_strtolower(request_string('stage', 40));
$stage = isset($options[$wanted]) ? $wanted : (array_search($wanted, array_map('mb_strtolower', $options), true) ?: '');
$input = ['to_stage' => $stage, 'moved_at' => batches_datetime_local(), 'volume_out_gal' => batches_l_to_volume($batch['current_volume_l'])];
log_screen_entered('batch-stage-move', 'batch', $id, $batch['number']);
render_screen('Move stage ' . $batch['number'], 'batch-stage-move', view('batches/partials/stage-form.php', ['batch' => $batch, 'input' => $input, 'errors' => [], 'stageOptions' => $options]), 'batch', $id);
