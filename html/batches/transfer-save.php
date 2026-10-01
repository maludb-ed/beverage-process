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
    render_screen($batch['number'], 'batch-transfer', view('shared/error.php', ['message' => 'Batch ' . $batch['number'] . ' is ' . $batch['status'] . ' and accepts no more events.']), 'batch', $id);
    exit;
}
$occupancies = find_batch_vessels($pdo, $id);
$catalog = batches_vessel_catalog($pdo);
$input = [
    'from_vessel_id' => request_integer('from_vessel_id'), 'to_vessel_id' => request_integer('to_vessel_id'), 'volume_gal' => request_string('volume_gal', 20),
    'loss_gal' => request_string('loss_gal', 20), 'transferred_at' => request_string('transferred_at', 20), 'note' => request_string('note', 2000),
];
$errors = [];
$from = null;
foreach ($occupancies as $occupancy) {
    if ((int) $occupancy['vessel_id'] === $input['from_vessel_id']) { $from = $occupancy; }
}
if ($from === null) { $errors['from_vessel'] = 'Choose a vessel the batch is in.'; }
$to = $catalog[$input['to_vessel_id'] ?? 0] ?? null;
if ($to === null) { $errors['to_vessel'] = 'Choose the receiving vessel.'; }
elseif ($from !== null && (int) $to['id'] === (int) $from['vessel_id']) { $errors['to_vessel'] = 'Choose a different vessel.'; }
elseif ($to['occupancy_id'] !== null && !($to['occupant_kind'] === 'batch' && (int) $to['occupant_id'] === $id)) { $errors['to_vessel'] = batches_occupied_message($to); }   // I3
$gal = post_decimal('volume_gal');
$volume = is_float($gal) ? batches_volume_to_l($gal) : null;
if ($volume === null || $volume <= 0) { $errors['volume_gal'] = 'Enter the volume moved.'; }
elseif ($from !== null && $volume > (float) $from['volume_l'] + 0.0005) { $errors['volume_gal'] = $from['vessel_name'] . ' holds only ' . fmt_qty($from['volume_l'], 'L') . ' of this batch.'; }
$lossGal = post_decimal('loss_gal');
$loss = $lossGal === null ? 0.0 : (is_float($lossGal) ? batches_volume_to_l($lossGal) : null);
if ($loss === null || $loss < 0) { $errors['loss_gal'] = 'Enter the loss, zero or more.'; }
elseif ($volume !== null && $loss > $volume) { $errors['loss_gal'] = 'The loss cannot be more than the volume moved.'; }
$at = batches_datetime_field($input['transferred_at'], $errors, 'transferred_at', 'Enter when the transfer happened.');

$warnings = [];
if ($errors === []) {
    $volume = min($volume, (float) $from['volume_l']);
    $already = $to['occupancy_id'] !== null ? (float) $to['occupancy_volume_l'] : 0.0;
    if (($warning = batches_capacity_warning($to, $already + $volume - $loss)) !== null) { $warnings[] = $warning; }   // I4
    try {
        $pdo->beginTransaction();
        $locked = find_batch($pdo, $id, true);
        $toLocked = find_vessel($pdo, (int) $to['id'], true);
        if ($locked['status'] !== 'active') { throw new RuntimeException('Batch ' . $batch['number'] . ' is no longer active.'); }
        if ($toLocked['occupancy_id'] !== null && !($toLocked['occupant_kind'] === 'batch' && (int) $toLocked['occupant_id'] === $id)) { throw new RuntimeException(batches_occupied_message($toLocked)); }
        $result = transfer_batch($pdo, $locked, $from, $toLocked, $volume, $loss, $at->format(DATE_ATOM), $input['note'] ?: null, (int) $user['id']);
        log_activity($pdo, 'batch_transferred', 'batch', $id, $batch['number'], ['vessel' => $from['vessel_name'], 'volume_l' => (float) $locked['current_volume_l']],
            ['from' => $from['vessel_name'], 'to' => $to['name'], 'volume_l' => $volume, 'loss_l' => $loss] + $result, $warnings !== [] ? ['capacity_warning' => true] : [], 'batch-transfer');
        $pdo->commit();
        foreach ($warnings as $warning) { flash('warning', $warning); }
        flash('success', fmt_qty($volume, 'L') . ' of ' . $batch['number'] . ' moved from ' . $from['vessel_name'] . ' to ' . $to['name'] . ($loss > 0 ? ' with ' . fmt_qty($loss, 'L', 2) . ' loss.' : '.'));
        hx_trigger('batchesChanged');
        hx_location('/batches/' . $id . '?tab=transfers');
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('batch transfer failed: ' . $exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The transfer could not be saved.') : $exception->getMessage();
    }
}
$input['transferred_at'] = $at?->format('Y-m-d\TH:i') ?? $input['transferred_at'];
http_response_code(422);
render_screen('Transfer ' . $batch['number'], 'batch-transfer', view('batches/partials/transfer-form.php', [
    'batch' => $batch, 'input' => $input, 'errors' => $errors, 'fromOptions' => batches_occupancy_options($occupancies), 'toOptions' => batches_vessel_options($catalog),
]), 'batch', $id);
