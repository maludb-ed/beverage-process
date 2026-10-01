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
    render_screen($batch['number'], 'batch-loss-add', view('shared/error.php', ['message' => 'Batch ' . $batch['number'] . ' is ' . $batch['status'] . ' and accepts no more events.']), 'batch', $id);
    exit;
}
$reasons = batches_reason_catalog($pdo, ['loss', 'dump']);
$stages = batches_stage_catalog($pdo);
$input = ['qty_gal' => request_string('qty_gal', 20), 'reason' => request_integer('reason'), 'stage' => request_string('stage', 20), 'occurred_at' => request_string('occurred_at', 20), 'note' => request_string('note', 2000)];
$errors = [];
$gal = post_decimal('qty_gal');
$qty = is_float($gal) ? batches_volume_to_l($gal) : null;
$current = (float) $batch['current_volume_l'];
if ($qty === null || $qty <= 0) { $errors['qty_gal'] = 'Enter the volume lost.'; }
elseif ($qty > $current + 0.0005) { $errors['qty_gal'] = 'The batch holds only ' . fmt_qty($current, 'L') . '.'; }
$reason = $reasons[$input['reason'] ?? 0] ?? null;
if ($reason === null) { $errors['reason'] = 'Choose a reason.'; }
elseif ($reason['classification'] === 'exceptional' && $input['note'] === '') { $errors['note'] = 'Explain an exceptional loss.'; }
if (!isset($stages[$input['stage']])) { $errors['stage'] = 'Choose a stage.'; }
$at = batches_datetime_field($input['occurred_at'], $errors, 'occurred_at', 'Enter when the loss happened.');

if ($errors === []) {
    $qty = min($qty, $current);
    try {
        $pdo->beginTransaction();
        $locked = find_batch($pdo, $id, true);
        if ($locked['status'] !== 'active' || $qty > (float) $locked['current_volume_l'] + 0.0005) { throw new RuntimeException('Batch ' . $batch['number'] . ' changed meanwhile; reload it.'); }
        $result = record_batch_loss($pdo, $locked, $qty, $reason, $input['stage'], $at->format(DATE_ATOM), $input['note'] ?: null, (int) $user['id']);
        log_activity($pdo, 'batch_loss_recorded', 'batch', $id, $batch['number'], ['volume_l' => $current],
            ['qty_l' => $qty, 'reason' => $reason['code'], 'ttb_category' => $reason['ttb_category'], 'classification' => $reason['classification']] + $result, [], 'batch-loss-add');
        $pdo->commit();
        if ($result['needs_approval']) {
            flash('warning', 'Loss of ' . fmt_qty($qty, 'L', 2) . ' is above the ' . fmt_qty($reason['requires_approval_above'], 'L', 2) . ' threshold for ' . $reason['name'] . ': it needs approval by compliance or the owner.');
        }
        flash('success', fmt_qty($qty, 'L', 2) . ' loss (' . $reason['name'] . ') recorded on ' . $batch['number'] . '.');
        hx_trigger('batchesChanged');
        hx_location('/batches/' . $id . '?tab=losses');
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('batch loss failed: ' . $exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The loss could not be saved.') : $exception->getMessage();
    }
}
$input['occurred_at'] = $at?->format('Y-m-d\TH:i') ?? $input['occurred_at'];
http_response_code(422);
render_screen('Loss ' . $batch['number'], 'batch-loss-add', view('batches/partials/loss-form.php', [
    'batch' => $batch, 'input' => $input, 'errors' => $errors, 'reasons' => $reasons, 'stages' => $stages,
]), 'batch', $id);
