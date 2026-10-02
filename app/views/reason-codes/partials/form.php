<?php /** @var array $reason  @var array $errors  @var ?bool $inputIsDisplay */
$id = $reason['id'] ?? null;
$isEdit = $id !== null;
$title = $isEdit ? 'Edit Reason Code' : 'Add Reason Code';
$p = 'reason-code-form';
$appliesTo = $reason['applies_to'] ?? 'loss';
$above = $reason['requires_approval_above'] ?? null;
if ($above !== null && $above !== '' && is_numeric($above) && empty($inputIsDisplay) && reason_code_threshold_is_volume($appliesTo)) {
    $above = round((float) to_display($above, 'L'), 4);   // stored liters -> display volume unit
}
if (is_numeric($above)) { $above = rtrim(rtrim(number_format((float) $above, 4, '.', ''), '0'), '.'); }
$volumeLabel = 'Approval needed above (' . display_unit('L') . ')';
$otherLabel = 'Approval needed above (item base unit)';
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'reason-code-form', 'crumbs' => ['Setup' => null, 'Reason codes' => '/reason-codes/', $isEdit ? 'Edit' : 'Add' => null], 'actionsHtml' => form_actions('reason-code-form', '/reason-codes/', 'Save Reason Code')]) ?>
<div class="main-content" id="reason-code-form-content">
    <form id="reason-code-form" method="post" action="/reason-codes/save" hx-post="/reason-codes/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <div class="row"><div class="col-lg-12">
            <div class="card" id="reason-code-form-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Reason code</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Reasons are chosen on adjustments, losses, overrides, counts, short closes and dumps.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => $errors, 'id' => 'reason-code-form-errors']) ?>
                    <?= form_input($p, 'code', 'Code', $reason['code'] ?? '', $errors, ['required' => true, 'maxlength' => 30, 'icon' => 'feather-hash', 'autofocus' => !$isEdit, 'help' => 'Stored in uppercase.']) ?>
                    <?= form_input($p, 'name', 'Name', $reason['name'] ?? '', $errors, ['required' => true, 'maxlength' => 120]) ?>
                    <?= form_select($p, 'applies_to', 'Applies to', REASON_APPLIES_TO, $appliesTo, $errors, ['required' => true,
                        'extra' => ' data-volume-label="' . e($volumeLabel) . '" data-other-label="' . e($otherLabel) . '" hx-on:change="var l=document.getElementById(\'reason-code-form-field-requires-approval-above-label\');l.textContent=(this.value===\'loss\'||this.value===\'dump\'?this.dataset.volumeLabel:this.dataset.otherLabel)+\':\';"']) ?>
                    <?= form_select($p, 'ttb_category', 'TTB category', REASON_TTB_CATEGORIES, $reason['ttb_category'] ?? 'none', $errors, ['required' => true]) ?>
                    <?= form_select($p, 'classification', 'Classification', REASON_CLASSIFICATIONS, $reason['classification'] ?? 'exceptional', $errors, ['required' => true]) ?>
                    <?= form_input($p, 'requires_approval_above', reason_code_threshold_is_volume($appliesTo) ? $volumeLabel : $otherLabel, $above ?? '', $errors, ['type' => 'number', 'min' => '0', 'step' => 'any', 'icon' => 'feather-alert-triangle', 'help' => 'Leave empty if approval is never required.']) ?>
                    <?= form_checkbox($p, 'active', 'Active', (bool) ($reason['active'] ?? true), ['last' => true]) ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
