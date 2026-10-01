<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/batches/queries.php';

// POST /batches/save pitches a new batch; POST /batches/{id}/save saves the edit form.
if (request_integer('id') !== null) {
    require __DIR__ . '/edit-save.php';
    return;
}
require_post();
verify_csrf();
$user = require_role('production');
$pdo = db();

$batch = [
    'product_id' => request_integer('product_id'),
    'recipe_version_id' => request_integer('recipe_version_id'),
    'production_order_id' => request_integer('production_order_id'),
    'vessel_id' => request_integer('vessel_id'),
    'yeast_lot_id' => request_integer('yeast_lot_id'),
    'yeast_qty' => request_string('yeast_qty', 20),
    'started_at' => request_string('started_at', 20),
    'notes' => request_string('notes', 2000),
];
$catalogs = batches_pitch_catalogs($pdo, $batch['product_id']);
$errors = [];
if ($batch['product_id'] === null || !isset($catalogs['products'][$batch['product_id']])) { $errors['product'] = 'Choose a product.'; }
if ($batch['recipe_version_id'] !== null && !isset($catalogs['recipes'][$batch['recipe_version_id']])) { $errors['recipe_version'] = 'That recipe version does not belong to the product.'; }
if ($batch['production_order_id'] !== null && !isset($catalogs['orders'][$batch['production_order_id']])) { $errors['production_order'] = 'Choose a released order for this product.'; }
$vessel = $catalogs['vesselCatalog'][$batch['vessel_id'] ?? 0] ?? null;
if ($vessel === null) { $errors['vessel'] = 'Choose the vessel the batch lives in.'; }
$yeast = $catalogs['yeastLots'][$batch['yeast_lot_id'] ?? 0] ?? null;
if ($yeast === null) { $errors['yeast_lot'] = 'Choose a released yeast lot with stock.'; }
$yeastQty = post_decimal('yeast_qty');
if ($yeastQty === false || $yeastQty === null || $yeastQty <= 0) { $errors['yeast_qty'] = 'Enter the yeast quantity.'; }
$startedAt = batches_datetime_field($batch['started_at'], $errors, 'started_at', 'Enter when the yeast was pitched.');

// Juice rows: released juice lots, volume ≤ what the vessel (or the stock) holds.
$juice = [];
$juiceErrors = [];
$picked = [];
foreach ((is_array($_POST['juice'] ?? null) ? $_POST['juice'] : []) as $n => $raw) {
    if (!is_array($raw)) { continue; }
    $lotId = (int) ($raw['lot_id'] ?? 0);
    $gal = batches_num($raw['volume_gal'] ?? '');
    if ($lotId === 0 && $gal === null) { continue; }
    $rowErrors = [];
    $lot = $catalogs['juiceLots'][$lotId] ?? null;
    if ($lot === null) { $rowErrors['lot_id'] = 'Choose a released juice lot.'; }
    elseif (isset($picked[$lotId])) { $rowErrors['lot_id'] = 'This lot is already on another row.'; }
    $liters = is_float($gal) ? batches_volume_to_l($gal) : null;
    if ($liters === null || $liters <= 0) { $rowErrors['volume_gal'] = 'Enter the volume pitched.'; }
    elseif ($lot !== null && $liters > (float) $lot['available_l'] + 0.0005) { $rowErrors['volume_gal'] = 'Only ' . fmt_qty($lot['available_l'], 'L') . ' of this lot is available.'; }
    if ($rowErrors !== []) { $juiceErrors[$n] = $rowErrors; }
    if ($lot !== null && $liters !== null) { $picked[$lotId] = [$lot, $liters]; }
    $juice[$n] = ['lot_id' => $lotId ?: null, 'volume_gal' => $raw['volume_gal'] ?? ''];
}
if ($juice === []) { $errors['juice'] = 'Add at least one juice lot.'; }
if ($juiceErrors !== []) { $errors['juice_rows'] = 'Fix the highlighted juice rows.'; }

// I3: the vessel must be empty unless it holds one of the selected juice lots, pitched in full.
$warnings = [];
if ($vessel !== null && $vessel['occupancy_id'] !== null) {
    $occupant = $vessel['occupant_kind'] === 'lot' ? ($picked[(int) $vessel['occupant_id']] ?? null) : null;
    if ($occupant === null) {
        $errors['vessel'] = batches_occupied_message($vessel);
    } elseif ($occupant[1] + 0.0005 < (float) $vessel['occupancy_volume_l']) {
        $errors['vessel'] = batches_occupied_message($vessel) . ' Pitch its whole ' . fmt_qty($vessel['occupancy_volume_l'], 'L') . ' to take the vessel over.';
    }
}
$volume = array_sum(array_map(static fn($j) => $j[1], $picked));
if ($vessel !== null && ($warning = batches_capacity_warning($vessel, $volume)) !== null) { $warnings[] = $warning; }

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $at = $startedAt->format(DATE_ATOM);
        $saved = pitch_batch($pdo, [
            'product_id' => $batch['product_id'], 'recipe_version_id' => $batch['recipe_version_id'], 'production_order_id' => $batch['production_order_id'],
            'vessel' => $vessel, 'juice' => array_values($picked), 'yeast' => $yeast, 'yeast_qty' => $yeastQty, 'started_at' => $at, 'notes' => $batch['notes'] ?: null,
        ], (int) $user['id']);
        log_activity($pdo, 'batch_pitched', 'batch', (int) $saved['id'], $saved['number'], null, [
            'number' => $saved['number'], 'product' => $catalogs['products'][$batch['product_id']], 'vessel' => $vessel['name'],
            'juice_lots' => array_map(static fn($j) => ['lot' => $j[0]['lot_number'], 'volume_l' => $j[1]], array_values($picked)),
            'yeast_lot' => $yeast['lot_number'], 'yeast_qty' => $yeastQty, 'volume_l' => (float) $saved['current_volume_l'], 'ledger_group_id' => $saved['ledger_group_id'],
        ], $warnings !== [] ? ['capacity_warning' => true] : [], 'batch-add');
        $pdo->commit();
        foreach ($warnings as $warning) { flash('warning', $warning); }
        flash('success', 'Batch ' . $saved['number'] . ' pitched in ' . $vessel['name'] . '.');
        hx_trigger('batchesChanged, lotsChanged, inventoryChanged');
        hx_location('/batches/' . $saved['id']);
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('batch pitch failed: ' . $exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? (is_unique_violation($exception) ? 'That vessel was filled meanwhile; check the tank board.' : 'The batch could not be pitched.')) : $exception->getMessage();
    }
}
http_response_code(422);
render_screen('Pitch a Batch', 'batch-add', view('batches/partials/form.php', ['batch' => $batch, 'juice' => $juice ?: ['n1' => []], 'errors' => $errors, 'juiceErrors' => $juiceErrors] + $catalogs), 'batch');
