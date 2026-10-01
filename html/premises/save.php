<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';

require_post();
verify_csrf();
$user = require_role();

$id = request_integer('id');
$input = [
    'id' => $id,
    'name' => request_string('name', 120),
    'kind' => request_string('kind', 20),
    'registry_number' => request_string('registry_number', 40),
    'report_form' => request_string('report_form', 10),
    'filing_frequency' => request_string('filing_frequency', 20),
    'tax_determination_point' => request_string('tax_determination_point', 20),
    'cbma_tier' => request_string('cbma_tier', 10),
    'active' => post_bool('active'),
];
$errors = [];
if ($input['name'] === '') { $errors['name'] = 'Name is required.'; }
if (!in_options($input['kind'], PREMISES_KINDS)) { $errors['kind'] = 'Choose a permit kind.'; }
if (!in_options($input['report_form'], PREMISES_FORMS)) { $errors['report_form'] = 'Choose a report form.'; }
elseif (($input['kind'] === 'bonded_winery') !== ($input['report_form'] === '5120.17')) { $errors['report_form'] = 'A bonded winery files 5120.17; a brewery files 5130.9 or 5130.26.'; }
if (!in_options($input['filing_frequency'], PREMISES_FREQUENCIES)) { $errors['filing_frequency'] = 'Choose a filing frequency.'; }
if (!in_options($input['tax_determination_point'], PREMISES_TAX_POINTS)) { $errors['tax_determination_point'] = 'Choose when tax is determined.'; }
if (!in_options($input['cbma_tier'], PREMISES_CBMA_TIERS)) { $errors['cbma_tier'] = 'Choose a CBMA tier.'; }

$pdo = db();
$before = $id !== null ? (find_premises($pdo, $id) ?? not_found('That premises does not exist.')) : null;

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $args = [$input['name'], $input['kind'], $input['registry_number'] ?: null, $input['report_form'], $input['filing_frequency'], $input['tax_determination_point'], $input['cbma_tier'], $input['active']];
        $premises = $id === null ? insert_premises($pdo, ...$args) : update_premises($pdo, $id, ...$args);
        log_activity($pdo, $id === null ? 'premises_created' : 'premises_updated', 'premises', (int) $premises['id'], $premises['name'],
            $before, $premises, [], $id === null ? 'premises-add' : 'premises-edit');
        $pdo->commit();
        flash('success', 'Premises "' . $premises['name'] . '" saved.');
        hx_trigger('premisesChanged');
        hx_location('/premises/');
    } catch (PDOException $exception) {
        $pdo->rollBack();
        error_log($exception->getMessage());
        $errors['form'] = db_error_message($exception) ?? 'The premises could not be saved.';
    }
}
http_response_code(422);
render_screen($id ? 'Edit Premises' : 'Add Premises', $id ? 'premises-edit' : 'premises-add', view('premises/partials/form.php', ['premises' => $input, 'errors' => $errors]), 'premises', $id);
