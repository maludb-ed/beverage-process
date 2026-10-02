<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/racks/queries.php';

$user = require_role();
$pdo = db();
$id = request_integer('id');
if ($id !== null) {
    $rack = find_rack($pdo, $id) ?? not_found('That rack does not exist.');
    $screen = 'rack-edit';
} else {
    $rack = ['parent_location_id' => request_integer('area_id'), 'rack_number' => strtoupper(request_string('rack_number', 12)), 'active' => true];
    $screen = 'rack-add';
}
$rack['from'] = request_string('from', 10) === 'area' ? 'area' : '';
log_screen_entered($screen, 'location', $id, $rack['name'] ?? null);
render_screen($id ? 'Edit Rack' : 'Add Rack', $screen, view('racks/partials/form.php', ['rack' => $rack, 'errors' => [], 'areaOptions' => rack_area_options($pdo)]), 'location', $id);
