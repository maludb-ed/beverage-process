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
    render_screen($batch['number'], 'batch-dump', view('shared/error.php', ['message' => 'Batch ' . $batch['number'] . ' is ' . $batch['status'] . ' and accepts no more events.']), 'batch', $id);
    exit;
}
$reasons = batches_reason_catalog($pdo, ['dump']);
$input = ['reason' => request_integer('reason'), 'note' => request_string('note', 2000), 'dumped_at' => request_string('dumped_at', 20)];
$errors = [];
$reason = $reasons[$input['reason'] ?? 0] ?? null;
if ($reason === null) { $errors['reason'] = 'Choose a dump reason.'; }
if ($input['note'] === '') { $errors['note'] = 'Say why the batch is dumped.'; }
$at = batches_datetime_field($input['dumped_at'], $errors, 'dumped_at', 'Enter when the batch was dumped.');

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $locked = find_batch($pdo, $id, true);
        if ($locked['status'] !== 'active') { throw new RuntimeException('Batch ' . $batch['number'] . ' is no longer active.'); }
        $vessels = array_column(find_batch_vessels($pdo, $id), 'vessel_name');
        $result = dump_batch($pdo, $locked, $reason, $at->format(DATE_ATOM), $input['note'], (int) $user['id']);
        log_activity($pdo, 'batch_dumped', 'batch', $id, $batch['number'], ['status' => 'active', 'volume_l' => (float) $locked['current_volume_l'], 'vessels' => $vessels],
            ['status' => 'dumped', 'reason' => $reason['code'], 'ttb_category' => $reason['ttb_category']] + $result, [], 'batch-dump');
        $pdo->commit();
        flash('success', $batch['number'] . ' dumped: ' . fmt_qty($result['volume_l'], 'L') . ' recorded as ' . humanize($reason['ttb_category']) . ($vessels !== [] ? '; ' . implode(', ', $vessels) . ' set to cleaning.' : '.'));
        hx_trigger('batchesChanged');
        hx_location('/batches/' . $id . '?tab=losses');
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('batch dump failed: ' . $exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The batch could not be dumped.') : $exception->getMessage();
    }
}
$input['dumped_at'] = $at?->format('Y-m-d\TH:i') ?? $input['dumped_at'];
http_response_code(422);
render_screen('Dump ' . $batch['number'], 'batch-dump', view('batches/partials/dump-form.php', ['batch' => $batch, 'input' => $input, 'errors' => $errors, 'reasons' => $reasons]), 'batch', $id);
