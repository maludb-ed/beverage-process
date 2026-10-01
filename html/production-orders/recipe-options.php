<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/production-orders/queries.php';

// Pattern A fragment: the recipe-version select for a product (default the active one),
// plus an out-of-band refresh of the planned volume field prefilled from the chosen recipe.
$user = require_role('production');
$pdo = db();
$productId = request_integer('product_id');
$recipes = $productId ? find_recipe_options($pdo, $productId) : [];
$selected = request_integer('recipe_version_id');
$chosen = $recipes[0] ?? null;
foreach ($recipes as $recipe) {
    if ((int) $recipe['id'] === $selected) { $chosen = $recipe; }
}
echo view('production-orders/recipe-options.php', [
    'recipes' => $recipes, 'selected' => $chosen['id'] ?? null,
    'volumeGal' => $chosen ? round((float) to_display($chosen['target_batch_volume_l'], 'L'), 1) : request_string('planned_volume_gal', 12),
    'productId' => $productId,
]);
