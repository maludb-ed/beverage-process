<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/lab/queries.php';

require_post();
verify_csrf();
$user = require_role('quality');
$pdo = db();
$types = find_measurement_types($pdo);
$kind = request_string('target_kind', 10) === 'lot' ? 'lot' : 'batch';
$input = [
    'target_kind' => $kind, 'target_id' => request_integer('target_id'), 'measurement_type_code' => request_string('measurement_type_code', 30),
    'value' => request_string('value', 20), 'taken_at' => request_string('taken_at', 20), 'stage_code' => request_string('stage_code', 30),
    'method' => request_string('method', 200), 'note' => request_string('note', 2000),
];
$errors = [];
$label = null;
$stageCode = null;
if ($input['target_id'] === null) {
    $errors['target_id'] = 'Choose a ' . $kind . '.';
} elseif ($kind === 'batch') {
    $batch = find_reading_batch($pdo, $input['target_id']);
    if ($batch === null || $batch['status'] !== 'active') {
        $errors['target_id'] = 'Choose an active batch.';
    } else {
        $label = $batch['number'];
        $allowed = find_stages_for_beverage($pdo, $batch['beverage_type']);
        $stageCode = $input['stage_code'] !== '' ? $input['stage_code'] : $batch['current_stage_code'];
        if (!isset($allowed[$stageCode])) { $errors['stage_code'] = 'Choose a stage this product passes through.'; }
    }
} else {
    $lot = find_reading_lot($pdo, $input['target_id']);
    if ($lot === null) { $errors['target_id'] = 'Choose a lot.'; } else { $label = $lot['lot_number']; }
}
$type = $types[$input['measurement_type_code']] ?? null;
$value = null;
if ($type === null) {
    $errors['measurement_type_code'] = 'Choose a measurement.';
}
$raw = str_replace(',', '', $input['value']);
if ($raw === '' || !is_numeric($raw)) {
    $errors['value'] = 'Enter the reading as a number.';
} elseif ($type !== null) {
    $value = (float) $raw;
    if (($type['min_valid'] !== null && $value < (float) $type['min_valid']) || ($type['max_valid'] !== null && $value > (float) $type['max_valid'])) {
        $errors['value'] = $type['name'] . ' must be between ' . (float) $type['min_valid'] . ' and ' . (float) $type['max_valid'] . ' ' . $type['unit'] . '.';
    }
}
$takenAt = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $input['taken_at'], new DateTimeZone((string) config('app.timezone')));
if ($takenAt === false || $takenAt->format('Y-m-d\TH:i') !== $input['taken_at']) { $errors['taken_at'] = 'Use a valid date and time.'; }

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $created = insert_reading($pdo, $kind, $input['target_id'], $input['measurement_type_code'], $value, $takenAt->format('c'), $stageCode, $input['method'] ?: null, true, (int) $user['id'], $input['note'] ?: null);
        $reading = find_reading($pdo, (int) $created['id']);
        $sentence = reading_spec_sentence($reading);
        log_activity($pdo, 'lab_reading_recorded', 'reading', (int) $created['id'], $label . ' ' . $type['name'],
            null, ['target' => $kind . ':' . $label, 'measurement' => $input['measurement_type_code'], 'value' => $value, 'spec_result' => $created['spec_result']], ['stage' => $stageCode], 'lab-reading-add');
        $pdo->commit();
        flash($created['spec_result'] === 'fail' ? 'warning' : 'success', $label . ': ' . $sentence . '.');
        hx_trigger('readingsChanged');
        hx_location('/lab/');
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('lab reading save failed: ' . $exception->getMessage());
        $errors['form'] = db_error_message($exception) ?? 'The reading could not be saved.';
    }
}
http_response_code(422);
render_screen('Record reading', 'lab-reading-add', view('lab/partials/form.php', lab_form_data($pdo, $input, $errors)));
