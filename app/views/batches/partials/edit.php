<?php /** @var array $batch  @var array $input  @var array $errors  @var array $reasons  @var bool $canOverride */
$id = (int) $batch['id'];
$p = 'batch-form';
$overrideOptions = ['none' => 'None (use the derived class)'] + BATCH_TAX_CLASSES;
$actions = '<a id="batch-form-cancel-btn" class="btn btn-light-brand" ' . nav_attrs('/batches/' . $id) . '><i class="feather-x me-2"></i><span>Cancel</span></a>'
    . '<button type="submit" form="batch-form" id="batch-form-save-btn" class="btn btn-primary"' . ($canOverride ? ' hx-confirm="Save ' . e($batch['number']) . '? A changed tax class override changes how this batch is taxed."' : '') . '><i class="feather-check me-2"></i><span>Save Batch</span></button>';
?>
<?= view('shared/page-header.php', ['title' => 'Edit ' . $batch['number'], 'screen' => 'batch-form', 'crumbs' => ['Production' => null, 'Batches' => '/batches/', $batch['number'] => '/batches/' . $id, 'Edit' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="batch-form-content">
    <form id="batch-form" method="post" action="/batches/<?= e($id) ?>/save" hx-post="/batches/<?= e($id) ?>/save" hx-target="#page-content" hx-swap="innerHTML"<?= $canOverride ? ' hx-confirm="Save ' . e($batch['number']) . '? A changed tax class override changes how this batch is taxed."' : '' ?>>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e($id) ?>" />
        <div class="row"><div class="col-lg-12">
            <div class="card" id="batch-form-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2"><?= e($batch['number']) ?> · <?= e($batch['product_name']) ?></span><span class="fs-12 fw-normal text-muted">Derived tax class: <?= e($batch['tax_class_derived'] ? humanize($batch['tax_class_derived']) : 'unknown') ?></span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'batch-form-errors']) ?>
                    <?= form_textarea($p, 'notes', 'Notes', $input['notes'] ?? '', $errors, ['rows' => 4]) ?>
                    <?= form_select($p, 'tax_class_override', 'Tax class override', $overrideOptions, $input['tax_class_override'] ?? 'none', $errors, ['disabled' => !$canOverride, 'help' => $canOverride ? 'Compliance only. Overrides the class derived from ABV, CO2 and fruit share.' : 'Only compliance or the owner can override the tax class.']) ?>
                    <?= form_select($p, 'tax_class_override_reason', 'Override reason', $reasons, $input['tax_class_override_reason'] ?? '', $errors, ['disabled' => !$canOverride, 'blank' => 'Required with an override', 'last' => true]) ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
