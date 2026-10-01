<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/press-runs/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/press-runs/validation.php';

require_post();
verify_csrf();
$user = require_role('production');
$pdo = db();
$id = request_integer('id');
$catalogs = press_run_form_catalogs($pdo);

$run = [
    'id' => $id,
    'premises_id' => request_integer('premises_id'),
    'press_vessel_id' => request_integer('press_vessel_id'),
    'run_on' => request_string('run_on', 10),
    'started_at' => request_string('started_at', 20),
    'finished_at' => request_string('finished_at', 20),
    'notes' => request_string('notes', 2000),
];
$errors = [];
if ($run['premises_id'] === null || !isset($catalogs['premises'][$run['premises_id']])) { $errors['premises'] = 'Choose a premises.'; }
if ($run['press_vessel_id'] !== null && !isset($catalogs['pressVessels'][$run['press_vessel_id']])) { $errors['press_vessel'] = 'Choose a press vessel or none.'; }
if (post_date('run_on') === false || $run['run_on'] === '') { $errors['run_on'] = 'Enter the press date.'; }
$started = $run['started_at'] !== '' ? batches_datetime_field($run['started_at'], $errors, 'started_at', 'Use a valid date and time.') : null;
$finished = $run['finished_at'] !== '' ? batches_datetime_field($run['finished_at'], $errors, 'finished_at', 'Use a valid date and time.') : null;
if ($started !== null && $finished !== null && $finished < $started) { $errors['finished_at'] = 'Finished must be at or after started.'; }

[$inputs, $inputErrors] = press_run_validate_inputs(is_array($_POST['inputs'] ?? null) ? $_POST['inputs'] : [], $catalogs['fruitLots']);
[$outputs, $outputErrors, $warnings] = press_run_validate_outputs(is_array($_POST['outputs'] ?? null) ? $_POST['outputs'] : [], $catalogs['outputItems'], $catalogs['vesselCatalog'], $catalogs['locations']);
if ($inputs === []) { $errors['inputs'] = 'Add at least one fruit lot.'; }
if (array_filter($outputs, static fn($o) => $o['kind'] === 'juice') === []) { $errors['outputs'] = 'Add at least one juice output.'; }
if ($inputErrors !== [] || $outputErrors !== []) { $errors['rows'] = 'Fix the highlighted rows.'; }

$before = null;
if ($id !== null) {
    $before = find_press_run($pdo, $id) ?? not_found('That press run does not exist.');
    if ($before['status'] !== 'draft') { $errors['form'] = 'Only draft press runs can be edited.'; }
    $run['number'] = $before['number'];
}

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $args = [(int) $run['premises_id'], $run['press_vessel_id'], $run['run_on'], $started?->format(DATE_ATOM), $finished?->format(DATE_ATOM), $run['notes'] ?: null];
        $saved = $id === null ? insert_press_run($pdo, ...[...$args, (int) $user['id']]) : update_press_run($pdo, $id, ...$args);
        replace_press_run_lines($pdo, (int) $saved['id'], $inputs, $outputs);
        $summary = ['inputs' => array_values(array_map(static fn($i) => ['lot_id' => $i['lot_id'], 'qty_kg' => $i['qty_kg']], $inputs)),
            'outputs' => array_values(array_map(static fn($o) => ['kind' => $o['kind'], 'item_id' => $o['item_id'], 'qty_base' => $o['qty_base'], 'vessel_id' => $o['vessel_id']], $outputs))];
        log_activity($pdo, $id === null ? 'press_run_created' : 'press_run_updated', 'press_run', (int) $saved['id'], $saved['number'],
            $before === null ? null : array_intersect_key($before, $saved), $saved + $summary, $warnings !== [] ? ['capacity_warning' => true] : [], $id === null ? 'press-run-add' : 'press-run-edit');
        $pdo->commit();
        foreach ($warnings as $warning) {
            flash('warning', $warning);
        }
        flash('success', 'Press run ' . $saved['number'] . ' saved as a draft. Post it to create the juice and pomace lots.');
        hx_trigger('pressRunsChanged');
        hx_location('/press-runs/' . $saved['id']);
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The press run could not be saved.') : $exception->getMessage();
    }
}
http_response_code(422);
render_screen($id ? 'Edit ' . $run['number'] : 'Add Press Run', $id ? 'press-run-edit' : 'press-run-add', view('press-runs/partials/form.php', [
    'run' => $run, 'inputs' => $inputs ?: ['n1' => []], 'outputs' => $outputs ?: ['n1' => ['kind' => 'juice']], 'errors' => $errors,
    'inputErrors' => $inputErrors, 'outputErrors' => $outputErrors,
] + $catalogs), 'press_run', $id);
