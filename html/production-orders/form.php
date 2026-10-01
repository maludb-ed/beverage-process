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
$vesselCatalog = production_vessel_catalog($pdo);

if ($id !== null) {
    $order = find_production_order($pdo, $id) ?? not_found('That production order does not exist.');
    if ($order['status'] !== 'planned') {
        hx_location('/production-orders/' . $id);
    }
    $order['planned_volume_gal'] = round((float) to_display($order['planned_volume_l'], 'L'), 1);
    $rows = [];
    foreach (find_production_order_vessels($pdo, $id) as $i => $row) {
        $rows['n' . ($i + 1)] = $row;
    }
    $screen = 'production-order-edit';
} else {
    // Prefill from the action manifest: ?product=<name>, ?volume_gal=, ?pitch_on=.
    $productName = mb_strtolower(request_string('product', 120));
    $productId = request_integer('product_id');
    foreach ($products as $pid => $name) {
        if ($productName !== '' && mb_strtolower($name) === $productName) { $productId = $pid; }
    }
    $pitch = request_string('pitch_on', 10);
    $order = [
        'product_id' => isset($products[$productId]) ? $productId : null, 'premises_id' => production_default_premises_id($premises),
        'planned_volume_gal' => request_string('volume_gal', 12), 'planned_pitch_on' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $pitch) ? $pitch : null,
    ];
    if ($order['product_id'] !== null) {
        $recipes = find_recipe_options($pdo, (int) $order['product_id']);
        $order['recipe_version_id'] = $recipes[0]['id'] ?? null;
        if ($order['planned_volume_gal'] === '' && $recipes !== []) {
            $order['planned_volume_gal'] = round((float) to_display($recipes[0]['target_batch_volume_l'], 'L'), 1);
        }
    }
    $rows = ['n1' => []];
    $screen = 'production-order-add';
}
log_screen_entered($screen, 'production_order', $id, $order['number'] ?? null);
render_screen($id ? 'Edit ' . $order['number'] : 'Add Production Order', $screen, view('production-orders/partials/form.php', [
    'order' => $order, 'rows' => $rows, 'errors' => [], 'rowErrors' => [], 'products' => $products, 'premises' => $premises, 'vesselCatalog' => $vesselCatalog,
    'recipes' => isset($order['product_id']) ? find_recipe_options($pdo, (int) $order['product_id']) : [],
]), 'production_order', $id);
