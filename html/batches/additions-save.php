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
    render_screen($batch['number'], 'batch-addition-add', view('shared/error.php', ['message' => 'Batch ' . $batch['number'] . ' is ' . $batch['status'] . ' and accepts no more events.']), 'batch', $id);
    exit;
}
$items = batches_addition_item_catalog($pdo);
$stages = batches_stage_catalog($pdo);
$input = [
    'item_id' => request_integer('item_id'), 'lot_id' => request_integer('lot_id'), 'unit' => request_string('unit', 20), 'qty' => request_string('qty', 20),
    'purpose' => request_string('purpose', 20), 'stage' => request_string('stage', 20), 'added_at' => request_string('added_at', 20), 'note' => request_string('note', 2000),
];
$errors = [];
$item = $items[$input['item_id'] ?? 0] ?? null;
if ($item === null) { $errors['item'] = 'Choose an item.'; }
$lots = $item !== null ? find_item_lot_options($pdo, (int) $item['id']) : [];
if ($item !== null && $input['lot_id'] === null && !$item['lot_controlled'] && $lots !== []) {
    $input['lot_id'] = array_key_first($lots);   // not lot-controlled: first lot FEFO
}
$lot = $lots[$input['lot_id'] ?? 0] ?? null;
if ($item !== null && $lot === null) { $errors['lot'] = 'Choose a released lot with stock.'; }
$factor = $item['units'][$input['unit']]['factor'] ?? null;
if ($item !== null && $factor === null) { $errors['unit'] = 'Choose a unit for this item.'; }
$qty = post_decimal('qty');
if ($qty === null || $qty === false || $qty <= 0) { $errors['qty'] = 'Enter a quantity above zero.'; }
if (!in_options($input['purpose'], BATCH_ADDITION_PURPOSES)) { $errors['purpose'] = 'Choose a purpose.'; }
if (!isset($stages[$input['stage']])) { $errors['stage'] = 'Choose a stage.'; }
$addedAt = batches_datetime_field($input['added_at'], $errors, 'added_at', 'Enter when it was added.');

if ($errors === []) {
    $qtyBase = round($qty * $factor, 4);
    try {
        $pdo->beginTransaction();
        $locked = find_batch($pdo, $id, true);
        if ($locked['status'] !== 'active') { throw new RuntimeException('Batch ' . $batch['number'] . ' is no longer active.'); }
        $result = record_batch_addition($pdo, $locked, $item, $lot, $qtyBase, $input['purpose'], $input['stage'], $addedAt->format(DATE_ATOM), $input['note'] ?: null, (int) $user['id']);
        log_activity($pdo, 'batch_addition_recorded', 'batch', $id, $batch['number'], ['volume_l' => (float) $locked['current_volume_l'], 'fruit_share_pct' => $locked['fruit_share_pct']],
            ['item' => $item['code'], 'lot' => $lot['lot_number'], 'qty_base' => $qtyBase, 'unit' => $item['base_unit_code'], 'purpose' => $input['purpose']] + $result, [], 'batch-addition-add');
        $pdo->commit();
        flash('success', fmt_qty($qtyBase, $item['base_unit_code'], 3) . ' of ' . $item['code'] . ' (' . $lot['lot_number'] . ') added to ' . $batch['number'] . '.');
        hx_trigger('batchesChanged, lotsChanged, inventoryChanged');
        hx_location('/batches/' . $id . '?tab=consumptions');
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('batch addition failed: ' . $exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The addition could not be saved.') : $exception->getMessage();
    }
}
$input['added_at'] = $addedAt?->format('Y-m-d\TH:i') ?? $input['added_at'];
http_response_code(422);
render_screen('Addition ' . $batch['number'], 'batch-addition-add', view('batches/partials/addition-form.php', [
    'batch' => $batch, 'input' => $input, 'errors' => $errors, 'items' => $items, 'item' => $item, 'lots' => $lots, 'stages' => $stages,
]), 'batch', $id);
