<?php /** @var array $product  @var array $errors  @var array $versionOptions  @var array $input */
$productId = (int) $product['id'];
$p = 'recipe-form';
$cancelUrl = '/products/' . $productId;
?>
<?= view('shared/page-header.php', ['title' => 'New recipe version', 'screen' => 'recipe-form', 'crumbs' => ['Products' => null, 'Products and recipes' => '/products/', $product['name'] => $cancelUrl, 'New version' => null], 'actionsHtml' => form_actions('recipe-form', $cancelUrl, 'Create Draft')]) ?>
<div class="main-content" id="recipe-form-content">
    <form id="recipe-form" method="post" action="/recipes/save" hx-post="/recipes/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" id="recipe-form-field-product" value="<?= e($productId) ?>" />
        <div class="row"><div class="col-lg-12">
            <div class="card" id="recipe-form-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">New version of <?= e($product['name']) ?></span><span class="fs-12 fw-normal text-muted text-truncate-1-line">A draft you can edit. Activate it to make it the recipe production uses.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'recipe-form-errors']) ?>
                    <?= form_input($p, 'batch_volume', 'Target batch volume (' . display_unit('L') . ')', $input['batch_volume'] ?? '', $errors, ['type' => 'number', 'min' => 0, 'step' => 'any', 'required' => true, 'icon' => 'feather-droplet', 'name' => 'batch_volume', 'suffix' => display_unit('L')]) ?>
                    <?= form_select($p, 'copy_from', 'Copy from', $versionOptions, $input['copy_from_version_id'] ?? '', $errors, ['blank' => 'Blank (start empty)', 'name' => 'copy_from_version_id', 'help' => 'Copies the stages and lines of that version.']) ?>
                    <?= form_textarea($p, 'change_note', 'Change note', $input['change_note'] ?? '', $errors, ['last' => true]) ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
