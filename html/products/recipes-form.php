<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/products/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/recipes/queries.php';

$user = require_role('production');
$pdo = db();
$productId = request_integer('id') ?? not_found('That product does not exist.');
$product = find_product($pdo, $productId) ?? not_found('That product does not exist.');
$options = recipe_version_options($pdo, $productId);
$copyFrom = request_integer('copy_from');
$gal = request_string('batch_volume_gal', 20);
$input = ['batch_volume' => is_numeric($gal) && (float) $gal > 0 ? $gal : '', 'copy_from_version_id' => isset($options[$copyFrom]) ? $copyFrom : null, 'change_note' => ''];
if ($input['batch_volume'] === '' && $input['copy_from_version_id'] !== null) {
    $source = find_recipe_version($pdo, $input['copy_from_version_id']);
    $input['batch_volume'] = round((float) to_display($source['target_batch_volume_l'], 'L'), 4);
}
log_screen_entered('recipe-add', 'product', $productId, $product['name']);
render_screen('New recipe version', 'recipe-add', view('recipes/partials/form.php', ['product' => $product, 'errors' => [], 'versionOptions' => $options, 'input' => $input]), 'product', $productId);
