<?php /** @var array $approval  @var array $errors  @var array $products  @var array $packages */
$id = $approval['id'] ?? null;
$isEdit = $id !== null;
$title = $isEdit ? 'Edit approval' : 'Record approval';
$p = 'approval-form';
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'approval-form', 'crumbs' => ['Products' => null, 'Approvals' => '/approvals/', $isEdit ? 'Edit' : 'Record' => null], 'actionsHtml' => form_actions('approval-form', '/approvals/', 'Save Approval')]) ?>
<div class="main-content" id="approval-form-content">
    <form id="approval-form" method="post" action="/approvals/save" hx-post="/approvals/save" hx-encoding="multipart/form-data" hx-target="#page-content" hx-swap="innerHTML" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <div class="row"><div class="col-lg-12">
            <div class="card stretch stretch-full" id="approval-form-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Regulatory approval</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Formula approvals and label approvals (COLA) per product.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'approval-form-errors']) ?>
                    <?= form_select($p, 'product', 'Product', $products, $approval['product_id'] ?? '', $errors, ['required' => true, 'blank' => 'Choose a product', 'name' => 'product_id',
                        'extra' => ' hx-get="/approvals/configs" hx-trigger="change" hx-target="#approval-form-field-packaging-config-row" hx-swap="outerHTML"']) ?>
                    <?= form_select($p, 'kind', 'Kind', APPROVAL_KINDS, $approval['kind'] ?? 'formula', $errors, ['required' => true]) ?>
                    <?= view('approvals/partials/package-select.php', ['packages' => $packages, 'selected' => $approval['packaging_configuration_id'] ?? '', 'errors' => $errors]) ?>
                    <?= form_input($p, 'reference_no', 'Reference number', $approval['reference_no'] ?? '', $errors, ['maxlength' => 80, 'icon' => 'feather-hash', 'help' => 'TTB formula id or COLA id.']) ?>
                    <?= form_select($p, 'status', 'Status', APPROVAL_STATUSES, $approval['status'] ?? 'required', $errors, ['required' => true]) ?>
                    <?= form_input($p, 'approved_on', 'Approved on', $approval['approved_on'] ?? '', $errors, ['type' => 'date', 'icon' => 'feather-calendar', 'help' => 'Required when the status is approved.']) ?>
                    <?= form_input($p, 'expires_on', 'Expires on', $approval['expires_on'] ?? '', $errors, ['type' => 'date', 'icon' => 'feather-calendar']) ?>
                    <?= form_row_open($p, 'attachment', 'Attachment') ?>
                        <input type="file" class="form-control<?= invalid_class($errors, 'attachment') ?>" id="<?= e(field_id($p, 'attachment')) ?>" name="attachment" accept=".pdf,.png,.jpg,.jpeg,application/pdf,image/png,image/jpeg" />
                        <?php if (isset($errors['attachment'])): ?><div class="invalid-feedback d-block"><?= e($errors['attachment']) ?></div><?php endif; ?>
                        <?php if (!empty($approval['attachment_name'])): ?><div class="fs-11 text-muted mt-1" id="approval-form-attachment-current">Current file: <a href="/approvals/<?= e($approval['id']) ?>/file" id="approval-form-attachment-link"><?= e($approval['attachment_name']) ?></a>. Uploading another replaces it.</div><?php endif; ?>
                    <?= form_row_close('PDF, PNG or JPG, up to 10 MB.') ?>
                    <?= form_textarea($p, 'notes', 'Notes', $approval['notes'] ?? '', $errors, ['last' => true]) ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
