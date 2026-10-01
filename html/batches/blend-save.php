<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/batches/queries.php';

require_post();
verify_csrf();
$user = require_role('production');
$pdo = db();
$catalog = batches_vessel_catalog($pdo);
$batches = batches_blend_input_options($pdo);
$products = batches_product_options($pdo);
$recipes = batches_all_recipe_options($pdo);
$input = [
    'vessel_id' => request_integer('vessel_id'), 'product_id' => request_integer('product_id'), 'recipe_version_id' => request_integer('recipe_version_id'),
    'blended_at' => request_string('blended_at', 20), 'note' => request_string('note', 2000),
];
$errors = [];
$rows = [];
$rowErrors = [];
$inputs = [];
foreach ((is_array($_POST['inputs'] ?? null) ? $_POST['inputs'] : []) as $n => $raw) {
    if (!is_array($raw)) { continue; }
    $batchId = (int) ($raw['batch_id'] ?? 0);
    $gal = batches_num($raw['volume_gal'] ?? '');
    if ($batchId === 0 && $gal === null) { continue; }
    $e = [];
    $source = $batches[$batchId] ?? null;
    if ($source === null) { $e['batch_id'] = 'Choose an active batch.'; }
    elseif (isset($inputs[$batchId])) { $e['batch_id'] = 'This batch is already on another row.'; }
    $liters = is_float($gal) ? batches_volume_to_l($gal) : null;
    if ($liters === null || $liters <= 0) { $e['volume_gal'] = 'Enter a volume above zero.'; }
    elseif ($source !== null && $liters > (float) $source['current_volume_l'] + 0.0005) { $e['volume_gal'] = $source['number'] . ' holds only ' . fmt_qty($source['current_volume_l'], 'L') . '.'; }
    if ($e !== []) { $rowErrors[$n] = $e; }
    elseif ($source !== null) { $inputs[$batchId] = [$source, min($liters, (float) $source['current_volume_l'])]; }
    $rows[$n] = ['batch_id' => $batchId ?: null, 'volume_gal' => $raw['volume_gal'] ?? ''];
}
if (count($rows) < 2) { $errors['inputs'] = 'Blend at least two batches.'; }
if ($rowErrors !== []) { $errors['rows'] = 'Fix the highlighted inputs.'; }
$vessel = $catalog[$input['vessel_id'] ?? 0] ?? null;
if ($vessel === null) { $errors['vessel'] = 'Choose the vessel for the blend.'; }
elseif ($vessel['occupancy_id'] !== null && !($vessel['occupant_kind'] === 'batch' && isset($inputs[(int) $vessel['occupant_id']]))) { $errors['vessel'] = batches_occupied_message($vessel); }   // I3
if ($input['product_id'] !== null && !isset($products[$input['product_id']])) { $errors['product'] = 'Choose an active product.'; }
$at = batches_datetime_field($input['blended_at'], $errors, 'blended_at', 'Enter when the batches were blended.');
// Product defaults to the product of the largest input.
$productId = $input['product_id'];
if ($productId === null && $inputs !== []) {
    $largest = array_reduce($inputs, static fn($carry, $i) => $carry === null || $i[1] > $carry[1] ? $i : $carry);
    $productId = (int) $largest[0]['product_id'];
}
if ($input['recipe_version_id'] !== null && ($recipes['products'][$input['recipe_version_id']] ?? null) !== $productId) { $errors['recipe_version'] = 'That recipe version belongs to another product.'; }
$total = array_sum(array_map(static fn($i) => $i[1], $inputs));
$warnings = [];
if ($vessel !== null && ($warning = batches_capacity_warning($vessel, $total)) !== null) { $warnings[] = $warning; }   // I4

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $locked = [];
        foreach ($inputs as $batchId => [$source, $liters]) {
            $row = find_batch($pdo, $batchId, true);
            if ($row['status'] !== 'active' || $liters > (float) $row['current_volume_l'] + 0.0005) { throw new RuntimeException('Batch ' . $row['number'] . ' changed meanwhile; reload the form.'); }
            $locked[] = [$row, $liters];
        }
        $result = blend_batches($pdo, $locked, find_vessel($pdo, (int) $vessel['id'], true), $productId, $input['recipe_version_id'], $at->format(DATE_ATOM), $input['note'] ?: null, (int) $user['id']);
        $number = $result['result']['number'];
        log_activity($pdo, 'batch_blended', 'batch', (int) $result['result']['id'], $number, null,
            ['result' => $number, 'vessel' => $vessel['name'], 'volume_l' => round($total, 3), 'inputs' => $result['inputs'], 'blend_id' => $result['blend_id']],
            $warnings !== [] ? ['capacity_warning' => true] : [], 'batch-blend-add');
        $pdo->commit();
        foreach ($warnings as $warning) { flash('warning', $warning); }
        flash('success', 'Blend ' . $number . ' created in ' . $vessel['name'] . ' from ' . implode(', ', array_column($result['inputs'], 'batch')) . '.');
        hx_trigger('batchesChanged');
        hx_location('/batches/' . $result['result']['id'] . '?tab=lineage');
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('batch blend failed: ' . $exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? (is_unique_violation($exception) ? 'That vessel was filled meanwhile; check the tank board.' : 'The blend could not be saved.')) : $exception->getMessage();
    }
}
$input['blended_at'] = $at?->format('Y-m-d\TH:i') ?? $input['blended_at'];
http_response_code(422);
render_screen('Blend', 'batch-blend-add', view('batches/partials/blend-form.php', [
    'input' => $input, 'rows' => $rows + (count($rows) < 2 ? ['n9' => []] : []), 'errors' => $errors, 'rowErrors' => $rowErrors,
    'batches' => $batches, 'vessels' => batches_vessel_options($catalog), 'products' => $products, 'recipes' => $recipes['labels'],
]), 'batch');
