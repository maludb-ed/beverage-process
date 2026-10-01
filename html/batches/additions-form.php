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
    render_screen($batch['number'], 'batch-addition-add', view('shared/error.php', ['message' => 'Batch ' . $batch['number'] . ' is ' . $batch['status'] . ' and accepts no more events.']), 'batch', $id);
    exit;
}
$items = batches_addition_item_catalog($pdo);
// Prefill (manifest): item by id or code, qty, purpose.
$wanted = request_string('item', 40);
$itemId = ctype_digit($wanted) && isset($items[(int) $wanted]) ? (int) $wanted : (array_search(mb_strtolower($wanted), array_map(static fn($i) => mb_strtolower($i['code']), $items), true) ?: null);
$item = $itemId !== null ? $items[$itemId] : null;
$lots = $item !== null ? find_item_lot_options($pdo, (int) $item['id']) : [];
$purpose = request_string('purpose', 20);
$input = [
    'item_id' => $itemId, 'lot_id' => $item !== null && $item['consumption_mode'] === 'backflush' && $lots !== [] ? array_key_first($lots) : '', 'unit' => $item['base_unit_code'] ?? '',
    'qty' => request_string('qty', 20), 'purpose' => in_options($purpose, BATCH_ADDITION_PURPOSES) ? $purpose : '', 'stage' => $batch['current_stage_code'], 'added_at' => batches_datetime_local(),
];
log_screen_entered('batch-addition-add', 'batch', $id, $batch['number']);
render_screen('Addition ' . $batch['number'], 'batch-addition-add', view('batches/partials/addition-form.php', [
    'batch' => $batch, 'input' => $input, 'errors' => [], 'items' => $items, 'item' => $item, 'lots' => $lots, 'stages' => batches_stage_catalog($pdo),
]), 'batch', $id);
