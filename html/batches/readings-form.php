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
    render_screen($batch['number'], 'batch-reading-add', view('shared/error.php', ['message' => 'Batch ' . $batch['number'] . ' is ' . $batch['status'] . ' and accepts no more events.']), 'batch', $id);
    exit;
}
$measurements = batches_measurement_catalog($pdo);
// Prefill (manifest): measurement by code or name, value.
$wanted = mb_strtolower(request_string('measurement', 60));
$code = isset($measurements[$wanted]) ? $wanted : (array_search($wanted, array_map(static fn($m) => mb_strtolower($m['name']), $measurements), true) ?: '');
$input = ['measurement' => $code, 'value' => request_string('value', 20), 'taken_at' => batches_datetime_local(), 'stage' => $batch['current_stage_code']];
log_screen_entered('batch-reading-add', 'batch', $id, $batch['number']);
render_screen('Reading ' . $batch['number'], 'batch-reading-add', view('batches/partials/reading-form.php', [
    'batch' => $batch, 'input' => $input, 'errors' => [], 'measurements' => $measurements, 'stages' => batches_stage_catalog($pdo),
]), 'batch', $id);
