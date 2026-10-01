<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/products/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/specs/queries.php';

require_post();
verify_csrf();
$user = require_role('quality');
$pdo = db();

$id = request_integer('id');
$before = $id !== null ? (find_spec($pdo, $id) ?? not_found('That spec does not exist.')) : null;
$productId = $before !== null ? (int) $before['product_id'] : request_integer('product_id');
$product = $productId !== null ? find_product($pdo, $productId) : null;
if ($product === null) {
    not_found('That product does not exist.');
}
$stages = product_stage_options($pdo, $product['beverage_type']);
$measurements = spec_measurement_options($pdo);
$min = post_decimal('min_value');
$max = post_decimal('max_value');
$target = post_decimal('target_value');
$input = [
    'id' => $id, 'product_id' => $productId, 'product_name' => $product['name'],
    'stage_code' => request_string('stage_code', 30), 'measurement_type_code' => request_string('measurement_type_code', 30),
    'min_value' => $min === false ? request_string('min_value', 20) : $min, 'max_value' => $max === false ? request_string('max_value', 20) : $max,
    'target_value' => $target === false ? request_string('target_value', 20) : $target, 'active' => post_bool('active'),
];
$errors = [];
if (!isset($stages[$input['stage_code']])) { $errors['stage'] = 'Choose a stage.'; }
if (!isset($measurements[$input['measurement_type_code']])) { $errors['measurement'] = 'Choose a measurement.'; }
if ($min === false) { $errors['min'] = 'Enter a number.'; }
if ($max === false) { $errors['max'] = 'Enter a number.'; }
if ($target === false) { $errors['target'] = 'Enter a number.'; }
if ($min === null && $max === null) { $errors['min'] = 'Enter a minimum, a maximum, or both.'; }
elseif ($min !== false && $max !== false && $min !== null && $max !== null && $min > $max) { $errors['max'] = 'The minimum cannot be above the maximum.'; }

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $args = [$productId, $input['stage_code'], $input['measurement_type_code'], $min, $max, $target, $input['active']];
        $spec = $id === null ? insert_spec($pdo, ...$args) : update_spec($pdo, $id, ...$args);
        log_activity($pdo, $id === null ? 'spec_created' : 'spec_updated', 'spec', (int) $spec['id'], $product['name'] . ' ' . $spec['stage_code'] . ' ' . $spec['measurement_type_code'],
            $before === null ? null : array_intersect_key($before, $spec), $spec, [], $id === null ? 'spec-add' : 'spec-edit');
        $pdo->commit();
        flash('success', 'Spec saved.');
        hx_trigger('specsChanged');
        hx_location('/products/' . $productId . '/specs');
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        if (is_unique_violation($exception)) {
            $errors['measurement'] = 'A spec for this measurement at this stage exists.';
        } else {
            $errors['form'] = db_error_message($exception) ?? 'The spec could not be saved.';
        }
    }
}
http_response_code(422);
render_screen($id ? 'Edit Spec' : 'Add Spec', $id ? 'spec-edit' : 'spec-add', view('specs/partials/form.php', ['spec' => $input, 'errors' => $errors, 'stages' => $stages, 'measurements' => $measurements]), 'spec', $id);
