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
    render_screen($batch['number'], 'batch-stage-move', view('shared/error.php', ['message' => 'Batch ' . $batch['number'] . ' is ' . $batch['status'] . ' and accepts no more events.']), 'batch', $id);
    exit;
}
$options = batches_next_stage_options($pdo, $batch);
$input = ['to_stage' => request_string('to_stage', 20), 'moved_at' => request_string('moved_at', 20), 'volume_out_gal' => request_string('volume_out_gal', 20), 'note' => request_string('note', 2000)];
$errors = [];
if (!isset($options[$input['to_stage']])) { $errors['to_stage'] = 'Choose a later stage.'; }
$movedAt = batches_datetime_field($input['moved_at'], $errors, 'moved_at', 'Enter when the batch moved.');
$gal = post_decimal('volume_out_gal');
$current = (float) $batch['current_volume_l'];
$volumeOut = $gal === null ? $current : (is_float($gal) ? batches_volume_to_l($gal) : null);
if ($volumeOut === null || $volumeOut <= 0) { $errors['volume_out_gal'] = 'Enter the volume leaving the stage.'; }
elseif ($volumeOut > $current + 0.0005) { $errors['volume_out_gal'] = 'The batch holds only ' . fmt_qty($current, 'L') . '.'; }

if ($errors === []) {
    $volumeOut = min($volumeOut, $current);
    try {
        $pdo->beginTransaction();
        $locked = find_batch($pdo, $id, true);
        if ($locked['status'] !== 'active' || $locked['current_stage_code'] !== $batch['current_stage_code']) { throw new RuntimeException('Batch ' . $batch['number'] . ' changed meanwhile; reload it.'); }
        $result = move_batch_stage($pdo, $locked, $input['to_stage'], $movedAt->format(DATE_ATOM), $volumeOut, $input['note'] ?: null, (int) $user['id']);
        log_activity($pdo, 'batch_stage_moved', 'batch', $id, $batch['number'], ['stage' => $batch['current_stage_code'], 'volume_l' => $current],
            ['stage' => $input['to_stage'], 'volume_l' => round($volumeOut, 3)] + $result, [], 'batch-stage-move');
        $pdo->commit();
        flash('success', $batch['number'] . ' moved to ' . $options[$input['to_stage']] . ($result['loss_l'] > 0 ? '; ' . fmt_qty($result['loss_l'], 'L', 2) . ' recorded as an expected loss.' : '.'));
        hx_trigger('batchesChanged');
        hx_location('/batches/' . $id);
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('batch stage move failed: ' . $exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The stage move could not be saved.') : $exception->getMessage();
    }
}
$input['moved_at'] = $movedAt?->format('Y-m-d\TH:i') ?? $input['moved_at'];
http_response_code(422);
render_screen('Move stage ' . $batch['number'], 'batch-stage-move', view('batches/partials/stage-form.php', ['batch' => $batch, 'input' => $input, 'errors' => $errors, 'stageOptions' => $options]), 'batch', $id);
