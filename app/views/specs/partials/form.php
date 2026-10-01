<?php /** @var array $spec  @var array $errors  @var array $stages  @var array $measurements */
$id = $spec['id'] ?? null;
$isEdit = $id !== null;
$productId = (int) $spec['product_id'];
$cancelUrl = '/products/' . $productId . '/specs';
$p = 'spec-form';
?>
<?= view('shared/page-header.php', ['title' => $isEdit ? 'Edit Spec' : 'Add Spec', 'screen' => 'spec-form', 'crumbs' => ['Products' => null, 'Products and recipes' => '/products/', ($spec['product_name'] ?? 'Product') => '/products/' . $productId, 'Specs' => $cancelUrl, $isEdit ? 'Edit' : 'Add' => null], 'actionsHtml' => form_actions('spec-form', $cancelUrl, 'Save Spec')]) ?>
<div class="main-content" id="spec-form-content">
    <form id="spec-form" method="post" action="/specs/save" hx-post="/specs/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <input type="hidden" name="product_id" id="spec-form-field-product" value="<?= e($productId) ?>" />
        <div class="row"><div class="col-lg-12">
            <div class="card stretch stretch-full" id="spec-form-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Spec for <?= e($spec['product_name'] ?? '') ?></span><span class="fs-12 fw-normal text-muted text-truncate-1-line">The acceptable range for a measurement at a stage, in the measurement's own unit.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'spec-form-errors']) ?>
                    <?= form_select($p, 'stage', 'Stage', $stages, $spec['stage_code'] ?? '', $errors, ['required' => true, 'blank' => 'Choose a stage', 'name' => 'stage_code']) ?>
                    <?= form_select($p, 'measurement', 'Measurement', $measurements, $spec['measurement_type_code'] ?? '', $errors, ['required' => true, 'blank' => 'Choose a measurement', 'name' => 'measurement_type_code']) ?>
                    <?= form_input($p, 'min', 'Minimum', $spec['min_value'] ?? '', $errors, ['type' => 'number', 'step' => 'any', 'icon' => 'feather-arrow-down', 'name' => 'min_value']) ?>
                    <?= form_input($p, 'max', 'Maximum', $spec['max_value'] ?? '', $errors, ['type' => 'number', 'step' => 'any', 'icon' => 'feather-arrow-up', 'name' => 'max_value', 'help' => 'Enter at least one of minimum and maximum.']) ?>
                    <?= form_input($p, 'target', 'Target', $spec['target_value'] ?? '', $errors, ['type' => 'number', 'step' => 'any', 'icon' => 'feather-target', 'name' => 'target_value']) ?>
                    <?= form_checkbox($p, 'active', 'Active', (bool) ($spec['active'] ?? true), ['last' => true]) ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
