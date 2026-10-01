<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/packaging-runs/queries.php';

// Pattern A fragments for the packaging run form. part=batch: the batch changed (package, vessel, location,
// materials and readback reload out of band). part=materials: the package changed or "Recalculate" was pressed.
// part=readback: volume, units, ABV or CO2 changed (the readback block only).
$user = require_role('production');
$pdo = db();
$part = request_string('part', 20);
$run = [
    'batch_id' => request_integer('batch_id'),
    'packaging_configuration_id' => request_integer('packaging_configuration_id'),
    'units_out' => request_string('units_out', 10),
    'volume_in_gal' => request_string('volume_in_gal', 20),
    'abv_at_packaging' => request_string('abv_at_packaging', 10),
    'co2_g_100ml' => request_string('co2_g_100ml', 10),
];
if ($part === 'batch') {
    $run['packaging_configuration_id'] = null;   // configurations belong to the batch's product
    $context = packaging_form_context($pdo, $run);
    foreach (['configurations' => 'packaging_configuration_id', 'vessels' => 'source_vessel_id', 'locations' => 'output_location_id'] as $list => $field) {
        if (count($context[$list]) === 1) {
            $run[$field] = array_key_first($context[$list]);
        }
    }
    $context = packaging_form_context($pdo, $run);
    $oob = static fn(string $id, string $html) => '<div id="' . e($id) . '" hx-swap-oob="innerHTML">' . $html . '</div>';
    $data = $context + ['run' => $run, 'errors' => [], 'materialErrors' => []];
    echo $oob('packaging-run-form-configuration-slot', view('packaging-runs/partials/slot-configuration.php', $data))
        . $oob('packaging-run-form-vessel-slot', view('packaging-runs/partials/slot-vessel.php', $data))
        . $oob('packaging-run-form-location-slot', view('packaging-runs/partials/slot-location.php', $data))
        . $oob('packaging-run-form-materials', view('packaging-runs/partials/materials.php', $data))
        . $oob('packaging-run-form-readback', view('packaging-runs/partials/readback.php', $data));
    exit;
}
$context = packaging_form_context($pdo, $run);
if ($part === 'materials') {
    $data = $context + ['materialErrors' => []];
    echo '<div id="packaging-run-form-materials" hx-swap-oob="innerHTML">' . view('packaging-runs/partials/materials.php', $data) . '</div>'
        . '<div id="packaging-run-form-readback" hx-swap-oob="innerHTML">' . view('packaging-runs/partials/readback.php', $data) . '</div>';
    exit;
}
echo view('packaging-runs/partials/readback.php', $context);
