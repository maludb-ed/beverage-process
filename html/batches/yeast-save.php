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
    render_screen($batch['number'], 'yeast-harvest-add', view('shared/error.php', ['message' => 'Batch ' . $batch['number'] . ' is ' . $batch['status'] . ' and accepts no more events.']), 'batch', $id);
    exit;
}
$items = batches_yeast_item_catalog($pdo);
$locations = batches_location_options($pdo, ['cold_room', 'freezer']);
$input = [
    'yeast_item_id' => request_integer('yeast_item_id'), 'generation' => request_string('generation', 5), 'volume_l' => request_string('volume_l', 20),
    'cell_count' => request_string('cell_count', 20), 'viability_pct' => request_string('viability_pct', 20), 'harvested_at' => request_string('harvested_at', 20),
    'location_id' => request_integer('location_id'), 'note' => request_string('note', 2000),
];
$errors = [];
$item = $items[$input['yeast_item_id'] ?? 0] ?? null;
if ($item === null) { $errors['yeast_item'] = 'Choose a yeast item.'; }
elseif ($item['base_unit_code'] !== 'L') { $errors['yeast_item'] = $item['code'] . ' is stocked in ' . $item['base_unit_code'] . '; harvested slurry is measured in liters, so choose a yeast item whose base unit is L.'; }
$generation = filter_var($input['generation'], FILTER_VALIDATE_INT);
if ($generation === false || $generation < 1) { $errors['generation'] = 'Generation is a whole number of 1 or more.'; }
$volume = post_decimal('volume_l');
if ($volume === null || $volume === false || $volume <= 0) { $errors['volume_l'] = 'Enter the slurry volume in liters.'; }
$cells = post_decimal('cell_count');
if ($cells === false || ($cells !== null && $cells < 0)) { $errors['cell_count'] = 'Enter a cell count of zero or more.'; }
$viability = post_decimal('viability_pct');
if ($viability === false || ($viability !== null && ($viability < 0 || $viability > 100))) { $errors['viability_pct'] = 'Viability is between 0 and 100 %.'; }
$at = batches_datetime_field($input['harvested_at'], $errors, 'harvested_at', 'Enter when the yeast was harvested.');
if (!isset($locations[$input['location_id'] ?? 0])) { $errors['location'] = 'Choose a cold room or freezer.'; }

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $locked = find_batch($pdo, $id, true);
        if ($locked['status'] !== 'active') { throw new RuntimeException('Batch ' . $batch['number'] . ' is no longer active.'); }
        $result = record_yeast_harvest($pdo, $locked, $item, (int) $generation, (float) $volume, $cells, $viability, $at->format(DATE_ATOM), (int) $input['location_id'],
            $input['note'] ?: null, find_batch_yeast($pdo, $id), (int) $user['id']);
        log_activity($pdo, 'yeast_harvested', 'batch', $id, $batch['number'], null,
            ['lot_number' => $result['lot_number'], 'lot_id' => $result['lot_id'], 'generation' => (int) $generation, 'volume_l' => (float) $volume, 'harvest_id' => $result['harvest_id']], [], 'yeast-harvest-add');
        $pdo->commit();
        flash('success', 'Yeast lot ' . $result['lot_number'] . ' (generation ' . (int) $generation . ', ' . number_format((float) $volume, 1) . ' L) harvested from ' . $batch['number'] . '.');
        hx_trigger('batchesChanged, lotsChanged, inventoryChanged');
        hx_location('/lots/' . $result['lot_id']);
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('yeast harvest failed: ' . $exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The harvest could not be saved.') : $exception->getMessage();
    }
}
$input['harvested_at'] = $at?->format('Y-m-d\TH:i') ?? $input['harvested_at'];
http_response_code(422);
render_screen('Harvest yeast ' . $batch['number'], 'yeast-harvest-add', view('batches/partials/yeast-form.php', [
    'batch' => $batch, 'input' => $input, 'errors' => $errors, 'yeastItems' => $items, 'locations' => $locations,
]), 'batch', $id);
