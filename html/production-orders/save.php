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
$catalog = reservation_resource_catalog($pdo);
$allowShare = double_booking_allowed($pdo);
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

// The equipment plan: plan[n][…] (the older vessels[n][vessel_id] shape is read too).
$rawRows = is_array($_POST['plan'] ?? null) ? $_POST['plan'] : (is_array($_POST['vessels'] ?? null) ? $_POST['vessels'] : []);
[$rows, $rowErrors] = production_validate_plan_rows($rawRows, $catalog);
if ($rows === []) { $errors['plan_rows'] = 'Add at least one vessel or piece of equipment to the plan.'; }

$before = null;
if ($id !== null) {
    $before = find_production_order($pdo, $id) ?? not_found('That production order does not exist.');
    if ($before['status'] !== 'planned') { $errors['form'] = 'Only planned production orders can be edited.'; }
    $order['number'] = $before['number'];
    // A row may name only this order's own bookings.
    $own = find_order_plan_ids($pdo, $id);
    foreach ($rows as &$row) { if ($row['id'] !== null && !in_array($row['id'], $own, true)) { $row['id'] = null; } }
    unset($row);
} else {
    foreach ($rows as &$row) { $row['id'] = null; }
    unset($row);
}
if ($rowErrors === []) {
    $rowErrors = production_plan_clashes($pdo, $rows, $catalog, $allowShare);
}
if ($rowErrors !== []) { $errors['plan_row_errors'] = 'Fix the highlighted plan rows.'; }

if ($errors === []) {
    $volumeL = from_display((float) $gal, 'L');
    try {
        $pdo->beginTransaction();
        $beforeRows = $id === null ? null : array_map('production_plan_row_from_reservation', find_order_plan($pdo, $id));
        $saved = $id === null
            ? insert_production_order($pdo, $order['premises_id'], $order['product_id'], $order['recipe_version_id'], $volumeL, $order['planned_pitch_on'], $order['planned_package_on'], $order['notes'] ?: null, (int) $user['id'])
            : update_production_order($pdo, $id, $order['product_id'], $order['recipe_version_id'], $volumeL, $order['planned_pitch_on'], $order['planned_package_on'], $order['notes'] ?: null, $order['premises_id']);
        save_production_order_plan($pdo, (int) $saved['id'], $rows, (int) $user['id']);
        $planAfter = array_map(static fn($r) => ['resource' => $catalog[$r['resource']]['name'], 'role' => $r['role'], 'from' => $r['planned_from'], 'to' => $r['planned_to'], 'all_day' => $r['all_day'], 'shared' => $r['shared']], array_values($rows));
        $sharedWith = [];
        foreach ($rows as $r) { foreach ($r['clash_rows'] ?? [] as $c) { $sharedWith[] = $c['subject_number'] ?? humanize($c['kind']); } }
        $sharedWith = array_values(array_unique($sharedWith));
        log_activity($pdo, $id === null ? 'production_order_created' : 'production_order_updated', 'production_order', (int) $saved['id'], $saved['number'],
            $before === null ? null : array_intersect_key($before, $saved) + ['plan' => $beforeRows], $saved + ['plan' => $planAfter],
            $sharedWith === [] ? [] : ['shared' => true, 'clashes' => $sharedWith], $id === null ? 'production-order-add' : 'production-order-edit');
        $pdo->commit();
        emit_action_status(true, ['record_id' => (int) $saved['id']]);
        flash('success', 'Production order ' . $saved['number'] . ' saved.' . ($sharedWith !== [] ? ' Equipment shared with ' . implode(', ', $sharedWith) . '.' : ''));
        hx_trigger('productionOrdersChanged');
        hx_location('/production-orders/' . $saved['id']);
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The production order could not be saved.') : $exception->getMessage();
    }
}
http_response_code(422);
if (isset($errors['plan_row_errors'])) {
    $clashLines = [];
    foreach ($rows as $n => $r) { if (isset($rowErrors[$n]['clashes'])) { $clashLines = array_merge($clashLines, reservation_clash_labels($catalog[$r['resource']]['name'] ?? 'The resource', $r['clash_rows'] ?? [])); } }
    emit_action_status(false, ['errors' => $errors, 'rows' => $rowErrors, 'clashes' => $clashLines, 'share_allowed' => $allowShare]);
}
render_screen($id ? 'Edit ' . $order['number'] : 'Add Production Order', $id ? 'production-order-edit' : 'production-order-add', view('production-orders/partials/form.php', [
    'order' => $order, 'rows' => $rows === [] ? ['n1' => []] : $rows, 'errors' => $errors, 'rowErrors' => $rowErrors,
    'products' => $products, 'premises' => $premises, 'groups' => reservation_resource_groups($catalog), 'catalog' => $catalog, 'recipes' => $recipes, 'allowShare' => $allowShare,
]), 'production_order', $id);
