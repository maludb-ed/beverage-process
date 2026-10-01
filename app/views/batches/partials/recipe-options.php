<?php /** @var array $recipes  @var mixed $recipeId  @var array $orders  @var mixed $orderId  @var array $errors */
$errors = $errors ?? [];
?>
<?= form_select('batch-form', 'recipe_version', 'Recipe version', $recipes, $recipeId ?? '', $errors, ['name' => 'recipe_version_id', 'blank' => $recipes === [] ? 'No recipe for this product' : 'No recipe']) ?>
<?= form_select('batch-form', 'production_order', 'Production order', $orders, $orderId ?? '', $errors, ['name' => 'production_order_id', 'blank' => $orders === [] ? 'No released orders for this product' : 'No order', 'help' => 'Pitching against an order moves it to in progress.']) ?>
