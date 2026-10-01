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
    render_screen($batch['number'], 'batch-dump', view('shared/error.php', ['message' => 'Batch ' . $batch['number'] . ' is ' . $batch['status'] . ' and accepts no more events.']), 'batch', $id);
    exit;
}
log_screen_entered('batch-dump', 'batch', $id, $batch['number']);
render_screen('Dump ' . $batch['number'], 'batch-dump', view('batches/partials/dump-form.php', [
    'batch' => $batch, 'input' => ['dumped_at' => batches_datetime_local()], 'errors' => [], 'reasons' => batches_reason_catalog($pdo, ['dump']),
]), 'batch', $id);
