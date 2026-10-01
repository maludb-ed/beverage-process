<?php /** @var array $lot  @var array $input  @var array $errors */
$id = (int) $lot['id'];
$p = 'lot-coa-form';
$values = $input['values'] ?? [];
for ($i = count($values); $i < 6; $i++) {
    $values[] = ['key' => '', 'value' => '', 'unit' => ''];
}
?>
<?= view('shared/page-header.php', ['title' => 'Certificate for ' . $lot['lot_number'], 'screen' => 'lot-coa-form', 'crumbs' => ['Receiving' => null, 'Lots' => '/lots/', $lot['lot_number'] => '/lots/' . $id, 'Certificate' => null], 'actionsHtml' => form_actions('lot-coa-form', '/lots/' . $id, 'Save Certificate')]) ?>
<div class="main-content" id="lot-coa-form-content">
    <form id="lot-coa-form" method="post" action="/lots/<?= e($id) ?>/coa/save" enctype="multipart/form-data" hx-post="/lots/<?= e($id) ?>/coa/save" hx-encoding="multipart/form-data" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <div class="row"><div class="col-lg-12">
            <div class="card stretch stretch-full" id="lot-coa-form-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Certificate of analysis</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">The values you enter are copied onto the lot so specs and recipes can use them.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'lot-coa-form-errors']) ?>
                    <?= form_input($p, 'issued_on', 'Issued on', $input['issued_on'] ?? '', $errors, ['type' => 'date', 'icon' => 'feather-calendar']) ?>
                    <?= form_input($p, 'issuer', 'Issuer', $input['issuer'] ?? '', $errors, ['maxlength' => 120, 'icon' => 'feather-award']) ?>
                    <?= form_row_open($p, 'file', 'Document') ?><input type="file" class="form-control<?= invalid_class($errors, 'file') ?>" id="lot-coa-form-field-file" name="file" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" /><?= isset($errors['file']) ? '<div class="invalid-feedback d-block">' . e($errors['file']) . '</div>' : '' ?><?= form_row_close('PDF, JPG or PNG up to 10 MB.') ?>
                    <div class="fw-semibold mb-2">Values</div>
                    <?php foreach ($values as $n => $value): ?>
                        <div class="row g-2 mb-2" id="lot-coa-form-value-<?= e($n) ?>">
                            <div class="col-12 col-md-5"><select class="form-select" id="lot-coa-form-value-<?= e($n) ?>-key" name="values[<?= e($n) ?>][key]" aria-label="Value name">
                                <option value="">—</option>
                                <?php foreach (LOT_ATTRIBUTE_KEYS as $key => $label): ?><option value="<?= e($key) ?>"<?= $value['key'] === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                            </select></div>
                            <div class="col-6 col-md-4"><input type="text" class="form-control" id="lot-coa-form-value-<?= e($n) ?>-value" name="values[<?= e($n) ?>][value]" value="<?= e($value['value']) ?>" placeholder="Value" aria-label="Value" maxlength="80" /></div>
                            <div class="col-6 col-md-3"><input type="text" class="form-control" id="lot-coa-form-value-<?= e($n) ?>-unit" name="values[<?= e($n) ?>][unit]" value="<?= e($value['unit']) ?>" placeholder="Unit" aria-label="Unit" maxlength="20" /></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
