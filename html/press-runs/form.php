<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/press-runs/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/press-runs/validation.php';

$user = require_role('production');
$pdo = db();
$id = request_integer('id');
$catalogs = press_run_form_catalogs($pdo);

if ($id !== null) {
    $run = find_press_run($pdo, $id) ?? not_found('That press run does not exist.');
    if ($run['status'] !== 'draft') {
        hx_location('/press-runs/' . $id);
    }
    [$inputs, $outputs] = press_run_form_rows(find_press_run_inputs($pdo, $id), find_press_run_outputs($pdo, $id));
    $run['started_at'] = $run['started_at'] ? batches_datetime_local($run['started_at']) : '';
    $run['finished_at'] = $run['finished_at'] ? batches_datetime_local($run['finished_at']) : '';
    $screen = 'press-run-edit';
} else {
    // Prefill: ?press=<press vessel name>.
    $press = mb_strtolower(request_string('press', 120));
    $match = $press !== '' ? array_search($press, array_map('mb_strtolower', $catalogs['pressVessels']), true) : false;
    $run = ['premises_id' => array_key_first($catalogs['premises']), 'press_vessel_id' => $match !== false ? $match : null];
    $inputs = [];
    $outputs = [];
    $screen = 'press-run-add';
}
$inputs = $inputs ?: ['n1' => []];
$outputs = $outputs ?: ['n1' => ['kind' => 'juice'], 'n2' => ['kind' => 'pomace']];
log_screen_entered($screen, 'press_run', $id, $run['number'] ?? null);
render_screen($id ? 'Edit ' . $run['number'] : 'Add Press Run', $screen, view('press-runs/partials/form.php', [
    'run' => $run, 'inputs' => $inputs, 'outputs' => $outputs, 'errors' => [], 'inputErrors' => [], 'outputErrors' => [],
] + $catalogs), 'press_run', $id);
