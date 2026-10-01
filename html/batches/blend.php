<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/batches/queries.php';

$user = require_role('production');
$pdo = db();
$catalog = batches_vessel_catalog($pdo);
$batches = batches_blend_input_options($pdo);
// Prefill (manifest): vessel by id or name; the batch it holds becomes the first input.
$wanted = request_string('vessel', 120);
$vesselId = ctype_digit($wanted) && isset($catalog[(int) $wanted]) ? (int) $wanted : (array_search(mb_strtolower($wanted), array_map(static fn($v) => mb_strtolower($v['name']), $catalog), true) ?: null);
$rows = ['n1' => [], 'n2' => []];
$vessel = $vesselId !== null ? $catalog[$vesselId] : null;
if ($vessel !== null && $vessel['occupant_kind'] === 'batch' && isset($batches[(int) $vessel['occupant_id']])) {
    $rows['n1'] = ['batch_id' => (int) $vessel['occupant_id'], 'volume_gal' => batches_l_to_volume($vessel['occupancy_volume_l'])];
}
$recipes = batches_all_recipe_options($pdo);
log_screen_entered('batch-blend-add', 'batch');
render_screen('Blend', 'batch-blend-add', view('batches/partials/blend-form.php', [
    'input' => ['vessel_id' => $vesselId, 'blended_at' => batches_datetime_local()], 'rows' => $rows, 'errors' => [], 'rowErrors' => [],
    'batches' => $batches, 'vessels' => batches_vessel_options($catalog), 'products' => batches_product_options($pdo), 'recipes' => $recipes['labels'],
]), 'batch');
