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
    render_screen($batch['number'], 'batch-loss-add', view('shared/error.php', ['message' => 'Batch ' . $batch['number'] . ' is ' . $batch['status'] . ' and accepts no more events.']), 'batch', $id);
    exit;
}
$reasons = batches_reason_catalog($pdo, ['loss', 'dump']);
// Prefill (manifest): qty_gal, reason by id or code.
$wanted = request_string('reason', 40);
$reasonId = ctype_digit($wanted) && isset($reasons[(int) $wanted]) ? (int) $wanted : (array_search(mb_strtoupper($wanted), array_map(static fn($r) => $r['code'], $reasons), true) ?: '');
$input = ['qty_gal' => request_string('qty_gal', 20), 'reason' => $reasonId, 'stage' => $batch['current_stage_code'], 'occurred_at' => batches_datetime_local()];
log_screen_entered('batch-loss-add', 'batch', $id, $batch['number']);
render_screen('Loss ' . $batch['number'], 'batch-loss-add', view('batches/partials/loss-form.php', [
    'batch' => $batch, 'input' => $input, 'errors' => [], 'reasons' => $reasons, 'stages' => batches_stage_catalog($pdo),
]), 'batch', $id);
