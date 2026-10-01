<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/locations/queries.php';

$user = require_role();
$id = request_integer('id');
if ($id !== null) {
    $location = find_location(db(), $id) ?? not_found('That location does not exist.');
    $screen = 'location-edit';
} else {
    $kind = request_string('kind');
    $location = ['name' => request_string('name', 120), 'kind' => in_options($kind, LOCATION_KINDS) ? $kind : 'cellar'];
    $screen = 'location-add';
}
log_screen_entered($screen, 'location', $id, $location['name'] ?? null);
render_screen($id ? 'Edit Location' : 'Add Location', $screen, view('locations/partials/form.php', ['location' => $location, 'errors' => [], 'premisesOptions' => premises_options(db())]), 'location', $id);
