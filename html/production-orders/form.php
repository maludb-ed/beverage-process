<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/production-orders/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';

$user = require_role('production');
$pdo = db();
$id = request_integer('id');
$products = production_product_options($pdo);
$premises = premises_options($pdo);
$catalog = reservation_resource_catalog($pdo);
$rows = [];
if ($id !== null) {
    $order = find_production_order($pdo, $id) ?? not_found('That production order does not exist.');
    if ($order['status'] !== 'planned') {
        flash('error', 'Only planned production orders can be edited.');
        hx_location('/production-orders/' . $id);
    }
    $order['planned_volume_gal'] = round(to_display($order['planned_volume_l'], 'L') ?? 0, 1);
    foreach (find_order_plan($pdo, $id) as $i => $row) {
        $rows['r' . $i] = production_plan_row_from_reservation($row);
    }
    $recipes = find_recipe_options($pdo, (int) $order['product_id']);
    $screen = 'production-order-edit';
} else {
    $productId = request_integer('product');
    $productId = $productId !== null && isset($products[$productId]) ? $productId : null;
    $volume = request_string('volume_gal', 12);
    $pitchOn = request_string('pitch_on', 10);
    $order = [
        'product_id' => $productId, 'recipe_version_id' => null, 'premises_id' => production_default_premises_id($premises),
        'planned_volume_gal' => is_numeric($volume) ? $volume : '', 'planned_pitch_on' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $pitchOn) ? $pitchOn : '',
        'planned_package_on' => '', 'notes' => '',
    ];
    $recipes = $productId !== null ? find_recipe_options($pdo, $productId) : [];
    if ($recipes !== [] && $order['planned_volume_gal'] === '') {
        $order['recipe_version_id'] = (int) $recipes[0]['id'];
        $order['planned_volume_gal'] = round(to_display($recipes[0]['target_batch_volume_l'], 'L') ?? 0, 1);
    }
    $screen = 'production-order-add';
}
if ($rows === []) {
    $rows = ['n1' => []];
}
log_screen_entered($screen, 'production_order', $id, $order['number'] ?? null);
render_screen($id ? 'Edit ' . $order['number'] : 'Add Production Order', $screen, view('production-orders/partials/form.php', [
    'order' => $order, 'rows' => $rows, 'errors' => [], 'rowErrors' => [], 'products' => $products, 'premises' => $premises,
    'groups' => reservation_resource_groups($catalog), 'catalog' => $catalog, 'recipes' => $recipes, 'allowShare' => double_booking_allowed($pdo),
]), 'production_order', $id);
