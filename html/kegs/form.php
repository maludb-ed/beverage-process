<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/kegs/queries.php';

$user = require_role('production');
$pdo = db();
$id = request_integer('id');
if ($id !== null) {
    $found = find_keg($pdo, $id) ?? not_found('That keg does not exist.');
    $keg = $found + ['size_gal' => round((float) liters_to_gal($found['size_l']), 2)];
    $screen = 'keg-edit';
} else {
    // Prefill: ?serial=KEG-0004&size=15.5 (gallons)
    $size = request_string('size', 10);
    $keg = ['serial' => request_string('serial', 60), 'size_gal' => is_numeric($size) ? $size : '', 'ownership' => 'owned', 'deposit_amount' => '0'];
    $screen = 'keg-add';
}
log_screen_entered($screen, 'keg', $id, $keg['serial'] !== '' ? $keg['serial'] : null);
render_screen($id ? 'Edit ' . $keg['serial'] : 'Add Keg', $screen, view('kegs/partials/form.php', ['keg' => $keg, 'errors' => []]), 'keg', $id);
