<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/vessels/queries.php';

$user = require_role('production');
$id = request_integer('id');
if ($id !== null) {
    $vessel = find_vessel(db(), $id) ?? not_found('That vessel does not exist.');
    $vessel['capacity'] = round(to_display($vessel['capacity_l'], 'L') ?? 0, 3);
    $screen = 'vessel-edit';
} else {
    $kind = request_string('kind');
    $capacity = request_string('capacity_gal', 20);
    $vessel = ['name' => request_string('name', 120), 'kind' => in_options($kind, VESSEL_KINDS) ? $kind : 'tank', 'capacity' => is_numeric($capacity) ? $capacity : ''];
    $screen = 'vessel-add';
}
log_screen_entered($screen, 'vessel', $id, $vessel['name'] ?? null);
render_screen($id ? 'Edit Vessel' : 'Add Vessel', $screen,
    view('vessels/partials/form.php', ['vessel' => $vessel, 'errors' => [], 'premisesOptions' => premises_options(db()), 'locationChoices' => vessel_location_choices(db())]), 'vessel', $id);
