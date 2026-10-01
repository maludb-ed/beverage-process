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
    render_screen($batch['number'], 'batch-split', view('shared/error.php', ['message' => 'Batch ' . $batch['number'] . ' is ' . $batch['status'] . ' and accepts no more events.']), 'batch', $id);
    exit;
}
$catalog = batches_vessel_catalog($pdo);
$input = ['split_at' => request_string('split_at', 20), 'note' => request_string('note', 2000)];
$errors = [];
$at = batches_datetime_field($input['split_at'], $errors, 'split_at', 'Enter when the batch was split.');
$rows = [];
$rowErrors = [];
$outputs = [];
$usesSourceVessel = false;
foreach ((is_array($_POST['outputs'] ?? null) ? $_POST['outputs'] : []) as $n => $raw) {
    if (!is_array($raw)) { continue; }
    $vesselId = (int) ($raw['vessel_id'] ?? 0);
    $gal = batches_num($raw['volume_gal'] ?? '');
    if ($vesselId === 0 && $gal === null) { continue; }
    $e = [];
    $vessel = $catalog[$vesselId] ?? null;
    $isSource = $vessel !== null && $vessel['occupant_kind'] === 'batch' && (int) $vessel['occupant_id'] === $id;
    if ($vessel === null) { $e['vessel_id'] = 'Choose a vessel.'; }
    elseif (isset($outputs[$vesselId])) { $e['vessel_id'] = 'Each output needs its own vessel.'; }
    elseif ($vessel['occupancy_id'] !== null && !$isSource) { $e['vessel_id'] = batches_occupied_message($vessel); }   // I3
    $liters = is_float($gal) ? batches_volume_to_l($gal) : null;
    if ($liters === null || $liters <= 0) { $e['volume_gal'] = 'Enter a volume above zero.'; }
    if ($e !== []) { $rowErrors[$n] = $e; }
    elseif ($liters !== null) { $outputs[$vesselId] = ['vessel' => $vessel, 'volume_l' => $liters]; $usesSourceVessel = $usesSourceVessel || $isSource; }
    $rows[$n] = ['vessel_id' => $vesselId ?: null, 'volume_gal' => $raw['volume_gal'] ?? ''];
}
$total = array_sum(array_column($outputs, 'volume_l'));
$current = (float) $batch['current_volume_l'];
if ($rows === []) { $errors['outputs'] = 'Add at least one output.'; }
if ($rowErrors !== []) { $errors['rows'] = 'Fix the highlighted outputs.'; }
elseif ($total > $current + 0.0005) { $errors['outputs'] = 'The outputs add up to ' . fmt_qty($total, 'L') . '; the batch holds ' . fmt_qty($current, 'L') . '.'; }
elseif ($usesSourceVessel && $total + 0.0005 < $current) { $errors['outputs'] = 'The remainder stays in the source vessel; split the whole volume to reuse that vessel, or choose another.'; }
$warnings = [];
foreach ($outputs as $output) {
    if (($warning = batches_capacity_warning($output['vessel'], $output['volume_l'])) !== null) { $warnings[] = $warning; }   // I4
}

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $locked = find_batch($pdo, $id, true);
        if ($locked['status'] !== 'active' || abs((float) $locked['current_volume_l'] - $current) > 0.0005) { throw new RuntimeException('Batch ' . $batch['number'] . ' changed meanwhile; reload it.'); }
        $result = split_batch($pdo, $locked, array_values($outputs), $at->format(DATE_ATOM), $input['note'] ?: null, (int) $user['id']);
        log_activity($pdo, 'batch_split', 'batch', $id, $batch['number'], ['volume_l' => $current], $result, $warnings !== [] ? ['capacity_warning' => true] : [], 'batch-split');
        $pdo->commit();
        foreach ($warnings as $warning) { flash('warning', $warning); }
        flash('success', $batch['number'] . ' split into ' . implode(', ', array_map(static fn($c) => $c['number'] . ' (' . $c['vessel'] . ')', $result['children'])) . '.');
        hx_trigger('batchesChanged');
        hx_location('/batches/' . $id . '?tab=lineage');
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('batch split failed: ' . $exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? (is_unique_violation($exception) ? 'A vessel was filled meanwhile; check the tank board.' : 'The split could not be saved.')) : $exception->getMessage();
    }
}
$input['split_at'] = $at?->format('Y-m-d\TH:i') ?? $input['split_at'];
http_response_code(422);
render_screen('Split ' . $batch['number'], 'batch-split', view('batches/partials/split-form.php', [
    'batch' => $batch, 'input' => $input, 'rows' => $rows ?: ['n1' => []], 'errors' => $errors, 'rowErrors' => $rowErrors, 'vessels' => batches_vessel_options($catalog),
]), 'batch', $id);
