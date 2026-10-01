<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/locations/queries.php';

require_post();
verify_csrf();
$user = require_role();

$pdo = db();
$id = request_integer('id');
$premisesOptions = premises_options($pdo);
$input = [
    'id' => $id,
    'premises_id' => request_integer('premises_id'),
    'name' => request_string('name', 120),
    'kind' => request_string('kind', 20),
    'tax_state' => request_string('tax_state', 20),
    'allow_negative' => post_bool('allow_negative'),
    'active' => post_bool('active'),
];
$before = $id !== null ? (find_location($pdo, $id) ?? not_found('That location does not exist.')) : null;

$errors = [];
if ($input['premises_id'] === null || (!array_key_exists($input['premises_id'], $premisesOptions) && (int) ($before['premises_id'] ?? 0) !== $input['premises_id'])) { $errors['premises_id'] = 'Choose a premises.'; }
if ($input['name'] === '') { $errors['name'] = 'Name is required.'; }
if (!in_options($input['kind'], LOCATION_KINDS)) { $errors['kind'] = 'Choose a location kind.'; }
if (!in_options($input['tax_state'], LOCATION_TAX_STATES)) { $errors['tax_state'] = 'Choose a tax state.'; }
elseif ($before !== null && $before['tax_state'] !== $input['tax_state'] && location_has_stock($pdo, $id)) { $errors['tax_state'] = 'Move stock out first.'; }

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $args = [$input['premises_id'], $input['name'], $input['kind'], $input['tax_state'], $input['allow_negative'], $input['active']];
        $location = $id === null ? insert_location($pdo, ...$args) : update_location($pdo, $id, ...$args);
        log_activity($pdo, $id === null ? 'location_created' : 'location_updated', 'location', (int) $location['id'], $location['name'],
            $before, $location, [], $id === null ? 'location-add' : 'location-edit');
        $pdo->commit();
        flash('success', 'Location "' . $location['name'] . '" saved.');
        hx_trigger('locationChanged');
        hx_location('/locations/');
    } catch (PDOException $exception) {
        $pdo->rollBack();
        if (is_unique_violation($exception)) {
            $errors['name'] = 'A location with this name exists on that premises.';
        } else {
            error_log($exception->getMessage());
            $errors['form'] = db_error_message($exception) ?? 'The location could not be saved.';
        }
    }
}
http_response_code(422);
render_screen($id ? 'Edit Location' : 'Add Location', $id ? 'location-edit' : 'location-add', view('locations/partials/form.php', ['location' => $input, 'errors' => $errors, 'premisesOptions' => $premisesOptions]), 'location', $id);
