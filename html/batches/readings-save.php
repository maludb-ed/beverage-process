<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/batches/queries.php';

require_post();
verify_csrf();
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
$stages = batches_stage_catalog($pdo);
$input = [
    'measurement' => request_string('measurement', 20), 'value' => request_string('value', 20), 'taken_at' => request_string('taken_at', 20),
    'stage' => request_string('stage', 20), 'method' => request_string('method', 80), 'note' => request_string('note', 2000),
];
$errors = [];
$type = $measurements[$input['measurement']] ?? null;
if ($type === null) { $errors['measurement'] = 'Choose a measurement.'; }
$value = post_decimal('value');
if ($value === null || $value === false) {
    $errors['value'] = 'Enter the value.';
} elseif ($type !== null && (($type['min_valid'] !== null && $value < (float) $type['min_valid']) || ($type['max_valid'] !== null && $value > (float) $type['max_valid']))) {
    $errors['value'] = $type['name'] . ' must be between ' . (float) $type['min_valid'] . ' and ' . (float) $type['max_valid'] . ' ' . $type['unit'] . '.';
}
$takenAt = batches_datetime_field($input['taken_at'], $errors, 'taken_at', 'Enter when the reading was taken.');
if ($input['stage'] !== '' && !isset($stages[$input['stage']])) { $errors['stage'] = 'Choose a cider stage.'; }

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $reading = record_batch_reading($pdo, $id, $input['measurement'], $value, $takenAt->format(DATE_ATOM), $input['stage'] ?: null, $input['method'] ?: null, $input['note'] ?: null, (int) $user['id']);
        log_activity($pdo, 'batch_reading_recorded', 'batch', $id, $batch['number'], null,
            ['reading_id' => (int) $reading['id'], 'measurement' => $input['measurement'], 'value' => $value, 'spec_result' => $reading['spec_result']], [], 'batch-reading-add');
        $pdo->commit();
        if ($reading['spec_result'] === 'fail') {
            $range = batches_spec_range($reading['min_value'], $reading['max_value']);
            flash('error', $type['name'] . ' ' . $value . ' ' . $type['unit'] . ' is out of spec: ' . $range . ' ' . $type['unit'] . '.');
        } else {
            flash('success', $type['name'] . ' ' . $value . ' ' . $type['unit'] . ' recorded on ' . $batch['number'] . ($reading['spec_result'] === 'pass' ? ' (in spec).' : '.'));
        }
        hx_trigger('batchesChanged');
        hx_location('/batches/' . $id . '?tab=readings');
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('batch reading failed: ' . $exception->getMessage());
        $errors['form'] = db_error_message($exception) ?? 'The reading could not be saved.';
    }
}
$input['taken_at'] = $takenAt?->format('Y-m-d\TH:i') ?? $input['taken_at'];
http_response_code(422);
render_screen('Reading ' . $batch['number'], 'batch-reading-add', view('batches/partials/reading-form.php', [
    'batch' => $batch, 'input' => $input, 'errors' => $errors, 'measurements' => $measurements, 'stages' => $stages,
]), 'batch', $id);
