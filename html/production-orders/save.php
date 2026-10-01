<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/production-orders/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';

require_post();
verify_csrf();
$user = require_role('production');
$pdo = db();

$id = request_integer('id');
$products = production_product_options($pdo);
$premises = premises_options($pdo);
$vesselCatalog = production_vessel_catalog($pdo);
$gal = post_decimal('planned_volume_gal');
$order = [
    'id' => $id,
    'product_id' => request_integer('product_id'),
    'recipe_version_id' => request_integer('recipe_version_id'),
    'premises_id' => request_integer('premises_id') ?? production_default_premises_id($premises),
    'planned_volume_gal' => $gal === false || $gal === null ? request_string('planned_volume_gal', 12) : $gal,
    'planned_pitch_on' => post_date('planned_pitch_on'),
    'planned_package_on' => post_date('planned_package_on'),
    'notes' => request_string('notes', 2000),
];
$errors = [];
if ($order['product_id'] === null || !isset($products[$order['product_id']])) { $errors['product'] = 'Choose a product.'; }
$recipes = $order['product_id'] !== null ? find_recipe_options($pdo, $order['product_id']) : [];
if ($order['recipe_version_id'] === null || !in_array($order['recipe_version_id'], array_map('intval', array_column($recipes, 'id')), true)) {
    $errors['recipe_version'] = 'Choose a recipe version that belongs to the product.';
}
if ($gal === false || $gal === null || $gal <= 0) { $errors['planned_volume_gal'] = 'Enter a planned volume above zero.'; }
if ($order['planned_pitch_on'] === false) { $errors['planned_pitch_on'] = 'Use a valid date.'; }
if ($order['planned_package_on'] === false) { $errors['planned_package_on'] = 'Use a valid date.'; }
if (is_string($order['planned_pitch_on']) && is_string($order['planned_package_on']) && $order['planned_package_on'] < $order['planned_pitch_on']) {
    $errors['planned_package_on'] = 'Package date must be on or after the pitch date.';
}
if ($order['premises_id'] === null || !isset($premises[$order['premises_id']])) { $errors['premises'] = 'Choose a premises.'; }

$rawRows = is_array($_POST['vessels'] ?? null) ? $_POST['vessels'] : [];
[$rows, $rowErrors] = production_validate_vessel_rows($rawRows, $vesselCatalog);
if ($rows === []) { $errors['vessel_rows'] = 'Add at least one vessel to the plan.'; }
if ($rowErrors !== []) { $errors['vessel_row_errors'] = 'Fix the highlighted vessel rows.'; }

$before = null;
if ($id !== null) {
    $before = find_production_order($pdo, $id) ?? not_found('That production order does not exist.');
    if ($before['status'] !== 'planned') { $errors['form'] = 'Only planned production orders can be edited.'; }
    $order['number'] = $before['number'];
}

if ($errors === []) {
    $volumeL = from_display((float) $gal, 'L');
    try {
        $pdo->beginTransaction();
        $beforeRows = $id === null ? null : find_production_order_vessels($pdo, $id);
        $saved = $id === null
            ? insert_production_order($pdo, $order['premises_id'], $order['product_id'], $order['recipe_version_id'], $volumeL, $order['planned_pitch_on'], $order['planned_package_on'], $order['notes'] ?: null, (int) $user['id'])
            : update_production_order($pdo, $id, $order['product_id'], $order['recipe_version_id'], $volumeL, $order['planned_pitch_on'], $order['planned_package_on'], $order['notes'] ?: null, $order['premises_id']);
        replace_production_order_vessels($pdo, (int) $saved['id'], $rows);
        log_activity($pdo, $id === null ? 'production_order_created' : 'production_order_updated', 'production_order', (int) $saved['id'], $saved['number'],
            $before === null ? null : array_intersect_key($before, $saved) + ['vessels' => $beforeRows], $saved + ['vessels' => array_values($rows)], [], $id === null ? 'production-order-add' : 'production-order-edit');
        $pdo->commit();
        flash('success', 'Production order ' . $saved['number'] . ' saved.');
        hx_trigger('productionOrdersChanged');
        hx_location('/production-orders/' . $saved['id']);
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The production order could not be saved.') : $exception->getMessage();
    }
}
http_response_code(422);
render_screen($id ? 'Edit ' . $order['number'] : 'Add Production Order', $id ? 'production-order-edit' : 'production-order-add', view('production-orders/partials/form.php', [
    'order' => $order, 'rows' => $rows === [] ? ['n1' => []] : $rows, 'errors' => $errors, 'rowErrors' => $rowErrors,
    'products' => $products, 'premises' => $premises, 'vesselCatalog' => $vesselCatalog, 'recipes' => $recipes,
]), 'production_order', $id);
