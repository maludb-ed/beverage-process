<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/racks/queries.php';

require_post();
verify_csrf();
$user = require_role();

$pdo = db();
$id = request_integer('id');
$areaOptions = rack_area_options($pdo);
$input = [
    'id' => $id,
    'parent_location_id' => request_integer('parent_location_id'),
    'rack_number' => strtoupper(request_string('rack_number', 20)),
    'active' => post_bool('active'),
    'from' => request_string('from', 10) === 'area' ? 'area' : '',
];
$before = $id !== null ? (find_rack($pdo, $id) ?? not_found('That rack does not exist.')) : null;

$errors = [];
if ($input['parent_location_id'] === null || (!array_key_exists($input['parent_location_id'], $areaOptions) && (int) ($before['parent_location_id'] ?? 0) !== $input['parent_location_id'])) { $errors['parent_location_id'] = 'Choose the area the rack stands in.'; }
elseif ($before !== null && (int) $before['parent_location_id'] !== $input['parent_location_id'] && rack_has_stock($pdo, $id)) { $errors['parent_location_id'] = 'Move the stock off this rack first.'; }
if ($input['rack_number'] === '') { $errors['rack_number'] = 'Rack number is required.'; }
elseif (!preg_match('/^[A-Z0-9-]{1,12}$/', $input['rack_number'])) { $errors['rack_number'] = 'Use up to 12 letters, digits or dashes, such as 7 or A-12.'; }

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $rack = $id === null
            ? insert_rack($pdo, $input['parent_location_id'], $input['rack_number'], $input['active'])
            : update_rack($pdo, $id, $input['parent_location_id'], $input['rack_number'], $input['active']);
        log_activity($pdo, $id === null ? 'rack_created' : 'rack_updated', 'location', (int) $rack['id'], $rack['name'],
            $before, $rack, [], $id === null ? 'rack-add' : 'rack-edit');
        $pdo->commit();
        flash('success', $rack['name'] . ' saved.');
        hx_trigger('locationChanged');
        hx_location($input['from'] === 'area' ? '/locations/' . (int) $rack['parent_location_id'] . '/edit' : '/racks/');
    } catch (PDOException $exception) {
        $pdo->rollBack();
        if (is_unique_violation($exception)) {
            $errors['rack_number'] = 'That rack number, or a location named "Rack ' . $input['rack_number'] . '", already exists on this premises.';
        } else {
            error_log($exception->getMessage());
            $errors['form'] = db_error_message($exception) ?? 'The rack could not be saved.';
        }
    }
}
http_response_code(422);
render_screen($id ? 'Edit Rack' : 'Add Rack', $id ? 'rack-edit' : 'rack-add', view('racks/partials/form.php', ['rack' => $input + ($before ?? []), 'errors' => $errors, 'areaOptions' => $areaOptions]), 'location', $id);
