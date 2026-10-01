<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/vessels/queries.php';

require_post();
verify_csrf();
$user = require_role('production');

$pdo = db();
$id = request_integer('id');
$premisesOptions = premises_options($pdo);
$locationChoices = vessel_location_choices($pdo);
$before = $id !== null ? (find_vessel($pdo, $id) ?? not_found('That vessel does not exist.')) : null;
$inUse = $before !== null && $before['status'] === 'in_use';

$capacity = post_decimal('capacity');
$input = [
    'id' => $id,
    'premises_id' => request_integer('premises_id'),
    'location_id' => request_integer('location_id'),
    'name' => request_string('name', 120),
    'kind' => request_string('kind', 20),
    'capacity' => $capacity === false ? request_string('capacity', 20) : $capacity,
    'status' => $inUse ? 'in_use' : request_string('status', 20),
    'notes' => request_string('notes', 2000),
    'active' => post_bool('active'),
];
$errors = [];
if ($input['premises_id'] === null || (!array_key_exists($input['premises_id'], $premisesOptions) && (int) ($before['premises_id'] ?? 0) !== $input['premises_id'])) { $errors['premises_id'] = 'Choose a premises.'; }
if ($input['location_id'] === null || !isset($locationChoices[$input['location_id']]) && (int) ($before['location_id'] ?? 0) !== $input['location_id']) { $errors['location_id'] = 'Choose a location.'; }
elseif ($input['premises_id'] !== null && isset($locationChoices[$input['location_id']]) && (int) $locationChoices[$input['location_id']]['premises_id'] !== $input['premises_id']) { $errors['location_id'] = 'That location belongs to a different premises.'; }
if ($input['name'] === '') { $errors['name'] = 'Name is required.'; }
if (!in_options($input['kind'], VESSEL_KINDS)) { $errors['kind'] = 'Choose a vessel kind.'; }
if ($capacity === null || $capacity === false || $capacity <= 0) { $errors['capacity'] = 'Capacity must be a number greater than zero.'; }
if (!$inUse && !in_options($input['status'], VESSEL_SETTABLE_STATUSES)) { $errors['status'] = 'Choose a status.'; }

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $args = [$input['premises_id'], $input['location_id'], $input['name'], $input['kind'], (float) from_display($capacity, 'L'), $input['status'], $input['notes'] ?: null, $input['active']];
        $vessel = $id === null ? insert_vessel($pdo, ...$args) : update_vessel($pdo, $id, ...$args);
        log_activity($pdo, $id === null ? 'vessel_created' : 'vessel_updated', 'vessel', (int) $vessel['id'], $vessel['name'],
            $before, $vessel, [], $id === null ? 'vessel-add' : 'vessel-edit');
        $pdo->commit();
        flash('success', 'Vessel "' . $vessel['name'] . '" saved.');
        hx_trigger('vesselChanged');
        hx_location('/vessels/');
    } catch (PDOException $exception) {
        $pdo->rollBack();
        if (is_unique_violation($exception)) {
            $errors['name'] = 'A vessel with this name exists on that premises.';
        } else {
            error_log($exception->getMessage());
            $errors['form'] = db_error_message($exception) ?? 'The vessel could not be saved.';
        }
    }
}
http_response_code(422);
render_screen($id ? 'Edit Vessel' : 'Add Vessel', $id ? 'vessel-edit' : 'vessel-add',
    view('vessels/partials/form.php', ['vessel' => $input, 'errors' => $errors, 'premisesOptions' => $premisesOptions, 'locationChoices' => $locationChoices]), 'vessel', $id);
