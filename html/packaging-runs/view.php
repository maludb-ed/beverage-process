<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/packaging-runs/queries.php';

$user = require_login();
$pdo = db();
$id = request_integer('id') ?? not_found('That packaging run does not exist.');
$run = find_packaging_run($pdo, $id) ?? not_found('That packaging run does not exist.');
$batch = find_packaging_batch($pdo, (int) $run['batch_id']);
$readback = packaging_readback($pdo, $batch, $run, $run['volume_in_l'] === null ? null : (float) $run['volume_in_l'], $run['units_out'] === null ? null : (int) $run['units_out'],
    $run['abv_at_packaging'] === null ? null : (float) $run['abv_at_packaging'], $run['co2_g_100ml'] === null ? null : (float) $run['co2_g_100ml']);
log_screen_entered('packaging-run-view', 'packaging_run', $id, $run['number']);
render_screen($run['number'], 'packaging-run-view', view('packaging-runs/partials/view.php', [
    'run' => $run, 'materials' => find_packaging_run_materials($pdo, $id), 'readback' => $readback, 'user' => $user,
]), 'packaging_run', $id);
