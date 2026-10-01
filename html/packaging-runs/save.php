<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/packaging-runs/queries.php';

require_post();
verify_csrf();
$user = require_role('production');
$pdo = db();

$id = request_integer('id');
$before = null;
if ($id !== null) {
    $before = find_packaging_run($pdo, $id) ?? not_found('That packaging run does not exist.');
}
$run = [
    'id' => $id, 'number' => $before['number'] ?? null,
    'batch_id' => request_integer('batch_id'),
    'packaging_configuration_id' => request_integer('packaging_configuration_id'),
    'run_on' => request_string('run_on', 10),
    'source_vessel_id' => request_integer('source_vessel_id'),
    'output_location_id' => request_integer('output_location_id'),
    'volume_in_gal' => request_string('volume_in_gal', 20),
    'units_out' => request_string('units_out', 10),
    'abv_at_packaging' => request_string('abv_at_packaging', 10),
    'co2_g_100ml' => request_string('co2_g_100ml', 10),
    'started_at' => request_string('started_at', 20),
    'finished_at' => request_string('finished_at', 20),
    'notes' => request_string('notes', 2000),
];
$errors = [];
if ($before !== null && $before['status'] !== 'draft') { $errors['form'] = 'Only draft packaging runs can be edited.'; }

$batches = packaging_batch_options($pdo, $before === null ? null : (int) $before['batch_id']);
$batch = $run['batch_id'] !== null && isset($batches[$run['batch_id']]) ? find_packaging_batch($pdo, $run['batch_id']) : null;
if ($batch === null) { $errors['batch_id'] = 'Choose an active batch that is ready for packaging.'; }
$configuration = null;
if ($batch !== null) {
    $configurations = packaging_configuration_options($pdo, (int) $batch['product_id'], $before === null ? null : (int) $before['packaging_configuration_id']);
    if ($run['packaging_configuration_id'] === null || !isset($configurations[$run['packaging_configuration_id']])) { $errors['packaging_configuration_id'] = 'Choose a package for this product.'; }
    else { $configuration = find_packaging_configuration($pdo, $run['packaging_configuration_id']); }
}
$runOn = post_date('run_on');
if ($runOn === null || $runOn === false) { $errors['run_on'] = 'Enter the run date.'; }

$vessels = $batch !== null ? find_packaging_batch_vessels($pdo, (int) $batch['id']) : [];
$occupancyL = null;
foreach ($vessels as $vessel) {
    if ((int) $vessel['vessel_id'] === $run['source_vessel_id']) { $occupancyL = (float) $vessel['volume_l']; }
}
if ($run['source_vessel_id'] === null || $occupancyL === null) { $errors['source_vessel_id'] = 'Choose a vessel the batch is in.'; }
$locations = $batch !== null ? packaging_output_location_options($pdo, (int) $batch['premises_id']) : [];
if ($run['output_location_id'] === null || !isset($locations[$run['output_location_id']])) { $errors['output_location_id'] = 'Choose a packaged goods location.'; }

$gallons = post_decimal('volume_in_gal');
$volumeInL = null;
if ($gallons === null || $gallons === false || $gallons <= 0) { $errors['volume_in_gal'] = 'Enter the volume taken, greater than zero.'; }
else {
    $volumeInL = round((float) gal_to_liters($gallons), 3);
    if ($occupancyL !== null && $volumeInL > $occupancyL + 0.0005) { $errors['volume_in_gal'] = 'The vessel holds only ' . number_format((float) liters_to_gal($occupancyL), 2) . ' gal.'; }
}
$unitsOut = null;
if ($run['units_out'] !== '') {
    if (!ctype_digit($run['units_out']) || (int) $run['units_out'] < 1) { $errors['units_out'] = 'Enter a whole number of units, at least 1.'; }
    else { $unitsOut = (int) $run['units_out']; }
}
$abv = post_decimal('abv_at_packaging');
if ($abv === false || ($abv !== null && ($abv < 0 || $abv > 25))) { $errors['abv_at_packaging'] = 'ABV must be between 0 and 25.'; $abv = null; }
$co2 = post_decimal('co2_g_100ml');
if ($co2 === false || ($co2 !== null && ($co2 < 0 || $co2 > 2))) { $errors['co2_g_100ml'] = 'CO2 must be between 0 and 2 g/100 mL.'; $co2 = null; }
if ($unitsOut !== null && $configuration !== null && $volumeInL !== null && round($unitsOut * (float) $configuration['fill_volume_l'], 3) > $volumeInL + 0.0005) {
    $errors['units_out'] = 'These units hold more than the volume taken.';
}
$zone = new DateTimeZone((string) config('app.timezone'));
$times = [];
foreach (['started_at', 'finished_at'] as $field) {
    $times[$field] = null;
    if ($run[$field] !== '') {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $run[$field], $zone);
        if ($parsed === false) { $errors[$field] = 'Enter a valid date and time.'; }
        else { $times[$field] = $parsed; }
    }
}
if ($times['started_at'] !== null && $times['finished_at'] !== null && $times['finished_at'] < $times['started_at']) { $errors['finished_at'] = 'Finish cannot be before the start.'; }

$postedMaterials = is_array($_POST['materials'] ?? null) ? $_POST['materials'] : [];
[$materialLines, $materialErrors] = packaging_validate_materials($pdo, $configuration['id'] ?? null, $batch === null ? null : (int) $batch['premises_id'], $postedMaterials);
if ($materialErrors !== []) { $errors['materials'] = 'Fix the highlighted materials.'; }

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $args = [(int) $batch['premises_id'], (int) $batch['id'], (int) $configuration['id'], $run['source_vessel_id'], $run['output_location_id'], (string) $runOn,
            $volumeInL, $unitsOut, $abv, $co2, $times['started_at']?->format(DATE_ATOM), $times['finished_at']?->format(DATE_ATOM), $run['notes'] ?: null];
        $saved = $id === null ? insert_packaging_run($pdo, ...$args, ...[(int) $user['id']]) : update_packaging_run($pdo, $id, ...$args);
        replace_packaging_run_materials($pdo, (int) $saved['id'], $materialLines);
        log_activity($pdo, $id === null ? 'packaging_run_created' : 'packaging_run_updated', 'packaging_run', (int) $saved['id'], $saved['number'],
            $before === null ? null : array_intersect_key($before, $saved), $saved + ['materials' => array_values($materialLines)], [], $id === null ? 'packaging-run-add' : 'packaging-run-edit');
        $pdo->commit();
        flash('success', 'Packaging run ' . $saved['number'] . ' saved as a draft. Post it to create the finished lot.');
        hx_trigger('packagingRunsChanged');
        hx_location('/packaging-runs/' . $saved['id']);
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The packaging run could not be saved.') : $exception->getMessage();
    }
}
$savedValues = [];
foreach ($postedMaterials as $itemId => $raw) {
    if (is_array($raw)) { $savedValues[(int) $itemId] = ['qty' => (string) ($raw['qty'] ?? ''), 'lot_id' => (int) ($raw['lot_id'] ?? 0) ?: null]; }
}
http_response_code(422);
render_screen($id ? 'Edit ' . ($run['number'] ?? 'Packaging Run') : 'Add Packaging Run', $id ? 'packaging-run-edit' : 'packaging-run-add',
    view('packaging-runs/partials/form.php', packaging_form_context($pdo, $run, $savedValues) + ['run' => $run, 'errors' => $errors, 'materialErrors' => $materialErrors]), 'packaging_run', $id);
