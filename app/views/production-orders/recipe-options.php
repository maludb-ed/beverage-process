<?php /** @var array $recipes  @var mixed $selected  @var mixed $volumeGal  @var bool $oob  @var array $errors */
$errors = $errors ?? [];
$oob = $oob ?? true;
$p = 'production-order-form';
$options = [];
foreach ($recipes as $recipe) {
    $options[$recipe['id']] = 'v' . $recipe['version_no'] . ' (' . $recipe['status'] . ')';
}
echo form_select($p, 'recipe_version', 'Recipe version', $options, $selected, $errors, [
    'required' => true, 'name' => 'recipe_version_id', 'blank' => $options === [] ? 'No recipe versions for this product' : null,
    'extra' => ' hx-get="/production-orders/recipe-options" hx-trigger="change" hx-target="#production-order-form-recipe-slot" hx-swap="innerHTML" hx-include="#production-order-form-field-product, #production-order-form-field-recipe-version"',
]);
if ($oob): ?>
<div id="production-order-form-volume-slot" hx-swap-oob="true">
    <?= form_input('production-order-form', 'planned_volume_gal', 'Planned volume', $volumeGal, [], ['type' => 'number', 'step' => '0.1', 'min' => '0', 'required' => true, 'icon' => 'feather-droplet', 'suffix' => display_unit('L')]) ?>
</div>
<?php endif; ?>
