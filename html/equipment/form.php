<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/equipment/queries.php';

$user = require_role('production');
$pdo = db();
$id = request_integer('id');
if ($id !== null) {
    $equipment = find_equipment($pdo, $id) ?? not_found('That equipment does not exist.');
    $screen = 'equipment-edit';
} else {
    $kind = request_string('kind', 20);
    $equipment = ['name' => request_string('name', 120), 'kind' => in_options($kind, EQUIPMENT_KINDS) ? $kind : 'other'];
    $screen = 'equipment-add';
}
log_screen_entered($screen, 'equipment', $id, $equipment['name'] ?? null);
render_screen($id ? 'Edit Equipment' : 'Add Equipment', $screen,
    view('equipment/partials/form.php', ['equipment' => $equipment, 'errors' => [], 'premisesOptions' => premises_options($pdo), 'locationChoices' => equipment_location_choices($pdo)]), 'equipment', $id);
