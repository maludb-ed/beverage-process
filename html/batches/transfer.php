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
    render_screen($batch['number'], 'batch-transfer', view('shared/error.php', ['message' => 'Batch ' . $batch['number'] . ' is ' . $batch['status'] . ' and accepts no more events.']), 'batch', $id);
    exit;
}
$occupancies = find_batch_vessels($pdo, $id);
$catalog = batches_vessel_catalog($pdo);
// Prefill (manifest): to_vessel by id or name, volume_gal, loss_gal.
$wanted = request_string('to_vessel', 120);
$toId = ctype_digit($wanted) && isset($catalog[(int) $wanted]) ? (int) $wanted : (array_search(mb_strtolower($wanted), array_map(static fn($v) => mb_strtolower($v['name']), $catalog), true) ?: '');
$input = [
    'from_vessel_id' => count($occupancies) === 1 ? (int) $occupancies[0]['vessel_id'] : '', 'to_vessel_id' => $toId,
    'volume_gal' => request_string('volume_gal', 20) ?: (count($occupancies) === 1 ? batches_l_to_volume($occupancies[0]['volume_l']) : ''),
    'loss_gal' => request_string('loss_gal', 20) ?: '0', 'transferred_at' => batches_datetime_local(),
];
log_screen_entered('batch-transfer', 'batch', $id, $batch['number']);
render_screen('Transfer ' . $batch['number'], 'batch-transfer', view('batches/partials/transfer-form.php', [
    'batch' => $batch, 'input' => $input, 'errors' => [], 'fromOptions' => batches_occupancy_options($occupancies), 'toOptions' => batches_vessel_options($catalog),
]), 'batch', $id);
