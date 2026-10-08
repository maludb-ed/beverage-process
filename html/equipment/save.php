<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/equipment/queries.php';

require_post();
verify_csrf();
$user = require_role('production');

$pdo = db();
$id = request_integer('id');
$premisesOptions = premises_options($pdo);
$locationChoices = equipment_location_choices($pdo);
$before = $id !== null ? (find_equipment($pdo, $id) ?? not_found('That equipment does not exist.')) : null;

$input = [
    'id' => $id,
    'premises_id' => request_integer('premises_id') ?? request_integer('premises') ?? (count($premisesOptions) === 1 ? (int) array_key_first($premisesOptions) : null),
    'location_id' => request_integer('location_id') ?? request_integer('location'),
    'name' => request_string('name', 120),
    'kind' => request_string('kind', 20),
    'status' => request_string('status', 20) ?: 'available',
    'rating' => request_string('rating', 120),
    'notes' => request_string('notes', 2000),
    'active' => $id === null ? true : post_bool('active'),
];
$errors = [];
if ($input['premises_id'] === null || !isset($premisesOptions[$input['premises_id']])) { $errors['premises_id'] = 'Choose a premises.'; }
if ($input['location_id'] !== null && !isset($locationChoices[$input['location_id']])) { $errors['location_id'] = 'Choose a location.'; }
elseif ($input['location_id'] !== null && $input['premises_id'] !== null && (int) $locationChoices[$input['location_id']]['premises_id'] !== $input['premises_id']) { $errors['location_id'] = 'That location belongs to a different premises.'; }
if ($input['name'] === '') { $errors['name'] = 'Name is required.'; }
if (!in_options($input['kind'], EQUIPMENT_KINDS)) { $errors['kind'] = 'Choose a kind of equipment.'; }
if (!in_options($input['status'], EQUIPMENT_STATUSES)) { $errors['status'] = 'Choose a status.'; }

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $args = [$input['premises_id'], $input['location_id'], $input['name'], $input['kind'], $input['status'], $input['rating'] ?: null, $input['notes'] ?: null, $input['active']];
        $equipment = $id === null ? insert_equipment($pdo, ...$args) : update_equipment($pdo, $id, ...$args);
        log_activity($pdo, $id === null ? 'equipment_created' : 'equipment_updated', 'equipment', (int) $equipment['id'], $equipment['name'],
            $before === null ? null : array_intersect_key($before, $equipment), $equipment, [], $id === null ? 'equipment-add' : 'equipment-edit');
        $pdo->commit();
        emit_action_status(true, ['record_id' => (int) $equipment['id']]);
        flash('success', 'Equipment "' . $equipment['name'] . '" saved.');
        hx_trigger('equipmentChanged');
        hx_location('/equipment/' . $equipment['id']);
    } catch (PDOException $exception) {
        $pdo->rollBack();
        if (is_unique_violation($exception)) {
            $errors['name'] = 'Equipment with this name exists on that premises.';
        } else {
            error_log($exception->getMessage());
            $errors['form'] = db_error_message($exception) ?? 'The equipment could not be saved.';
        }
    }
}
http_response_code(422);
render_screen($id ? 'Edit Equipment' : 'Add Equipment', $id ? 'equipment-edit' : 'equipment-add',
    view('equipment/partials/form.php', ['equipment' => $input, 'errors' => $errors, 'premisesOptions' => $premisesOptions, 'locationChoices' => $locationChoices]), 'equipment', $id);
