<?php
/**
 * Full-container form for an event posted onto a batch (reading, addition, stage move,
 * transfer, split, loss, dump, yeast harvest): page header with Cancel/Save, one card.
 * @var array $batch  @var string $formId  @var string $action  @var string $title  @var string $saveLabel
 * @var string $intro  @var string $bodyHtml  @var array $errors  @var ?string $confirm (hx-confirm on Save, destructive only)
 */
$id = (int) $batch['id'];
$cancelUrl = '/batches/' . $id;
$actions = form_actions($formId, $cancelUrl, $saveLabel);
if (!empty($confirm)) {
    $actions = '<a id="' . e($formId) . '-cancel-btn" class="btn btn-light-brand" ' . nav_attrs($cancelUrl) . '><i class="feather-x me-2"></i><span>Cancel</span></a>'
        . '<button type="submit" form="' . e($formId) . '" id="' . e($formId) . '-save-btn" class="btn btn-danger" hx-confirm="' . e($confirm) . '"><i class="feather-alert-triangle me-2"></i><span>' . e($saveLabel) . '</span></button>';
}
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => $formId, 'crumbs' => ['Production' => null, 'Batches' => '/batches/', $batch['number'] => $cancelUrl, $title => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="<?= e($formId) ?>-content">
    <form id="<?= e($formId) ?>" method="post" action="<?= e($action) ?>" hx-post="<?= e($action) ?>" hx-target="#page-content" hx-swap="innerHTML"<?= !empty($confirm) ? ' hx-confirm="' . e($confirm) . '"' : '' ?>>
        <?= csrf_field() ?>
        <div class="row"><div class="col-lg-12">
            <div class="card" id="<?= e($formId) ?>-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2"><?= e($batch['number']) ?> · <?= e($batch['product_name']) ?></span><span class="fs-12 fw-normal text-muted"><?= e($batch['stage_name']) ?> · <?= e(fmt_qty($batch['current_volume_l'], 'L')) ?><?= $intro !== '' ? ' · ' . e($intro) : '' ?></span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => $formId . '-errors']) ?>
                    <?= $bodyHtml ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
