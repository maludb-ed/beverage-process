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
    render_screen($batch['number'], 'yeast-harvest-add', view('shared/error.php', ['message' => 'Batch ' . $batch['number'] . ' is ' . $batch['status'] . ' and accepts no more events.']), 'batch', $id);
    exit;
}
$items = batches_yeast_item_catalog($pdo);
$pitched = find_batch_yeast($pdo, $id);
// Default item: the pitched yeast when it is measured in liters, else the first yeast item in liters.
$itemId = $pitched !== null && ($items[(int) $pitched['item_id']]['base_unit_code'] ?? '') === 'L' ? (int) $pitched['item_id'] : null;
foreach ($items as $candidate) {
    if ($itemId === null && $candidate['base_unit_code'] === 'L') { $itemId = (int) $candidate['id']; }
}
$locations = batches_location_options($pdo, ['cold_room', 'freezer']);
$input = [
    'yeast_item_id' => $itemId, 'generation' => request_string('generation', 5) ?: (string) ($pitched !== null && $pitched['generation'] !== null ? (int) $pitched['generation'] + 1 : 1),
    'volume_l' => request_string('volume_l', 20), 'harvested_at' => batches_datetime_local(),
];
log_screen_entered('yeast-harvest-add', 'batch', $id, $batch['number']);
render_screen('Harvest yeast ' . $batch['number'], 'yeast-harvest-add', view('batches/partials/yeast-form.php', [
    'batch' => $batch, 'input' => $input, 'errors' => [], 'yeastItems' => $items, 'locations' => $locations,
]), 'batch', $id);
