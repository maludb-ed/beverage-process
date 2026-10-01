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
    render_screen($batch['number'], 'batch-split', view('shared/error.php', ['message' => 'Batch ' . $batch['number'] . ' is ' . $batch['status'] . ' and accepts no more events.']), 'batch', $id);
    exit;
}
log_screen_entered('batch-split', 'batch', $id, $batch['number']);
render_screen('Split ' . $batch['number'], 'batch-split', view('batches/partials/split-form.php', [
    'batch' => $batch, 'input' => ['split_at' => batches_datetime_local()], 'rows' => ['n1' => [], 'n2' => []], 'errors' => [], 'rowErrors' => [],
    'vessels' => batches_vessel_options(batches_vessel_catalog($pdo)),
]), 'batch', $id);
