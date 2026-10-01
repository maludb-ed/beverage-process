<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/batches/queries.php';

// Pattern A fragment: recipe version (default the active one) and released production orders for the chosen product.
$user = require_role('production');
$pdo = db();
$productId = request_integer('product_id');
echo view('batches/partials/recipe-options.php', [
    'recipes' => batches_recipe_options($pdo, $productId), 'recipeId' => $productId ? batches_active_recipe_id($pdo, $productId) : null,
    'orders' => batches_production_order_options($pdo, $productId), 'orderId' => null,
]);
