<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/packaging-runs/queries.php';

$user = require_role('production');
$pdo = db();
$id = request_integer('id');
$savedMaterials = [];

if ($id !== null) {
    $found = find_packaging_run($pdo, $id) ?? not_found('That packaging run does not exist.');
    if ($found['status'] !== 'draft') {
        hx_location('/packaging-runs/' . $id);
    }
    $run = [
        'id' => $id, 'number' => $found['number'], 'batch_id' => (int) $found['batch_id'], 'packaging_configuration_id' => (int) $found['packaging_configuration_id'],
        'run_on' => $found['run_on'], 'source_vessel_id' => $found['source_vessel_id'], 'output_location_id' => (int) $found['output_location_id'],
        'volume_in_gal' => $found['volume_in_l'] === null ? '' : round((float) liters_to_gal($found['volume_in_l']), 2), 'units_out' => $found['units_out'] ?? '',
        'abv_at_packaging' => $found['abv_at_packaging'] ?? '', 'co2_g_100ml' => $found['co2_g_100ml'] ?? '', 'started_at' => $found['started_at'], 'finished_at' => $found['finished_at'],
        'notes' => $found['notes'],
    ];
    foreach (find_packaging_run_materials($pdo, $id) as $material) {
        $kind = $material['item_class'] === 'fruit' ? 'fruit' : 'default';
        $savedMaterials[(int) $material['item_id']] = ['qty' => round((float) to_display($material['qty_base'], $material['base_unit_code'], $kind), 4), 'lot_id' => $material['lot_id'] === null ? null : (int) $material['lot_id']];
    }
    $screen = 'packaging-run-edit';
} else {
    // Prefill from the batch view: /packaging-runs/new?batch=B-26-001&package=<configuration id or name>
    $run = ['batch_id' => null, 'run_on' => today()];
    $batch = ($number = request_string('batch', 40)) !== '' ? find_packaging_batch_by_number($pdo, $number) : null;
    if ($batch !== null) {
        $run['batch_id'] = (int) $batch['id'];
        $configs = packaging_configuration_rows($pdo, (int) $batch['product_id']);
        $package = mb_strtolower(request_string('package', 120));
        $match = array_values(array_filter($configs, static fn($c) => $package !== '' && ((string) $c['id'] === $package || mb_strtolower($c['name']) === $package)));
        if ($match === [] && $package !== '') {
            $match = array_values(array_filter($configs, static fn($c) => $c['package_kind'] === $package));
        }
        if (count($match) === 1) {
            $run['packaging_configuration_id'] = (int) $match[0]['id'];
        } elseif (count($configs) === 1) {
            $run['packaging_configuration_id'] = (int) $configs[0]['id'];
        }
        $vessels = packaging_vessel_options($pdo, (int) $batch['id']);
        if (count($vessels) === 1) { $run['source_vessel_id'] = array_key_first($vessels); }
        $locations = packaging_output_location_options($pdo, (int) $batch['premises_id']);
        if (count($locations) === 1) { $run['output_location_id'] = array_key_first($locations); }
    }
    $screen = 'packaging-run-add';
}
log_screen_entered($screen, 'packaging_run', $id, $run['number'] ?? null);
render_screen($id ? 'Edit ' . $run['number'] : 'Add Packaging Run', $screen, view('packaging-runs/partials/form.php',
    packaging_form_context($pdo, $run, $savedMaterials) + ['run' => $run, 'errors' => [], 'materialErrors' => []]), 'packaging_run', $id);
