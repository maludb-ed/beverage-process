<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/packaging-configs/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/packaging-configs/validation.php';
require_once dirname(__DIR__, 2) . '/app/features/products/queries.php';

require_post();
verify_csrf();
$user = require_role('production');
$pdo = db();

$id = request_integer('id');
$catalog = packaging_bom_item_catalog($pdo);
$products = products_options($pdo);
$finishedItems = packaging_finished_item_options($pdo);
$fillUnits = packaging_fill_unit_options();
$fill = post_decimal('fill_volume');
$loss = post_decimal('expected_loss_pct');
$upcRaw = request_string('units_per_case', 6);
// Only owner and sales see and set the list price; anyone else's save leaves it as it is.
$canPrice = user_can($user, 'sales');
$price = $canPrice ? post_decimal('default_unit_price') : null;
$config = [
    'id' => $id,
    'product_id' => request_integer('product_id'),
    'finished_item_id' => request_integer('finished_item_id'),
    'name' => request_string('name', 120),
    'package_kind' => request_string('package_kind', 10),
    'fill_unit' => request_string('fill_unit', 10),
    'fill_volume' => $fill === false ? request_string('fill_volume', 20) : $fill,
    'units_per_case' => $upcRaw,
    'expected_loss_pct' => $loss === false ? request_string('expected_loss_pct', 10) : $loss,
    'active' => post_bool('active'),
    'default_unit_price' => $price === false ? request_string('default_unit_price', 20) : $price,
];
$errors = [];
$before = null;
if ($id !== null) {
    $before = find_packaging_configuration($pdo, $id) ?? not_found('That packaging configuration does not exist.');
}
// An inactive product already on the configuration stays selectable when editing.
if ($before !== null && !isset($products[(int) $before['product_id']])) {
    $products = products_options($pdo, true);
}
if ($config['product_id'] === null || !isset($products[$config['product_id']])) { $errors['product'] = 'Choose a product.'; }
if ($config['finished_item_id'] === null || !isset($finishedItems[$config['finished_item_id']])) { $errors['finished_item'] = 'Choose a finished item.'; }
if ($config['name'] === '') { $errors['name'] = 'Name is required.'; }
if (!in_options($config['package_kind'], PACKAGE_KINDS)) { $errors['package_kind'] = 'Choose a package kind.'; }
if (!in_options($config['fill_unit'], $fillUnits)) { $errors['fill_unit'] = 'Choose a unit.'; }
if ($fill === null || $fill === false || $fill <= 0) { $errors['fill_volume'] = 'Enter a fill volume above zero.'; }
$unitsPerCase = null;
if ($upcRaw !== '') {
    if (!ctype_digit($upcRaw) || (int) $upcRaw < 1) { $errors['units_per_case'] = 'Units per case must be a whole number of 1 or more.'; }
    else { $unitsPerCase = (int) $upcRaw; }
} elseif ($config['package_kind'] !== 'keg' && in_options($config['package_kind'], PACKAGE_KINDS)) {
    $errors['units_per_case'] = 'Units per case is required for cans and bottles.';
}
if ($canPrice && ($price === false || ($price !== null && $price < 0))) { $errors['default_unit_price'] = 'Enter a price of zero or more, or leave it blank.'; }
if ($loss === null || $loss === false || $loss < 0 || $loss > 100) { $errors['expected_loss'] = 'Expected loss must be between 0 and 100.'; }

[$lines, $lineErrors] = validate_bom_lines(is_array($_POST['bom'] ?? null) ? $_POST['bom'] : [], $catalog);
if ($lineErrors !== []) { $errors['bom'] = 'Fix the highlighted bill of materials lines.'; }

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $fillL = $fill * unit_factor($config['fill_unit']);
        $bomLines = array_values(array_map(static fn(array $l) => ['item_id' => (int) $l['item_id'], 'qty_per_unit_base' => $l['qty_per_unit_base']], $lines));
        $args = [(int) $config['product_id'], (int) $config['finished_item_id'], $config['name'], $config['package_kind'], $fillL, $unitsPerCase, (float) $loss, $config['active'], $bomLines];
        $saved = $id === null ? insert_packaging_configuration($pdo, ...$args) : update_packaging_configuration($pdo, $id, ...$args);
        if ($canPrice) {
            $saved['default_unit_price'] = set_packaging_configuration_price($pdo, (int) $saved['id'], $price);
        }
        log_activity($pdo, $id === null ? 'packaging_config_created' : 'packaging_config_updated', 'packaging_configuration', (int) $saved['id'], $saved['name'],
            $before, $saved, [], $id === null ? 'packaging-config-add' : 'packaging-config-edit');
        $pdo->commit();
        flash('success', 'Packaging configuration "' . $saved['name'] . '" saved.');
        hx_trigger('packagingConfigsChanged');
        hx_location('/packaging-configs/');
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        if (is_unique_violation($exception)) {
            $errors['finished_item'] = 'This product already has a configuration for that item.';
        } else {
            $errors['form'] = db_error_message($exception) ?? 'The packaging configuration could not be saved.';
        }
    }
}
http_response_code(422);
render_screen($id ? 'Edit ' . $config['name'] : 'Add Packaging Configuration', $id ? 'packaging-config-edit' : 'packaging-config-add', view('packaging-configs/partials/form.php', [
    'config' => $config, 'lines' => $lines === [] ? ['n1' => []] : $lines, 'errors' => $errors, 'lineErrors' => $lineErrors, 'catalog' => $catalog, 'canPrice' => $canPrice,
    'products' => $products, 'finishedItems' => $finishedItems, 'fillUnits' => $fillUnits,
]), 'packaging_configuration', $id);
