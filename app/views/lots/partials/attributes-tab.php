<?php /** @var array $lot  @var array $attributes  @var array $errors  @var bool $canEdit  @var array $input */
$id = (int) $lot['id'];
$input = $input ?? [];
?>
<div class="table-responsive">
    <table class="table table-hover mb-0" id="lot-view-attributes-table">
        <thead class="thead-light"><tr><th>Attribute</th><th>Value</th><th>Source</th><th>Recorded</th></tr></thead>
        <tbody>
        <?php foreach ($attributes as $attribute): $aid = (int) $attribute['id']; ?>
            <tr id="lot-attribute-row-<?= e($aid) ?>">
                <td id="lot-attribute-row-<?= e($aid) ?>-key"><?= e(LOT_ATTRIBUTE_KEYS[$attribute['key']] ?? $attribute['key']) ?></td>
                <td id="lot-attribute-row-<?= e($aid) ?>-value" class="fw-semibold"><?= e($attribute['value_text'] ?? rtrim(rtrim(number_format((float) $attribute['value_num'], 4, '.', ','), '0'), '.')) ?> <small class="text-muted"><?= e($attribute['unit_code'] ?? '') ?></small></td>
                <td id="lot-attribute-row-<?= e($aid) ?>-source"><?= badge(humanize($attribute['source']), 'secondary') ?></td>
                <td id="lot-attribute-row-<?= e($aid) ?>-recorded"><?= e(format_date($attribute['recorded_at'])) ?> <small class="text-muted"><?= e($attribute['recorded_by_name'] ?? '') ?></small></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($attributes === []): ?><tr id="lot-view-attributes-empty"><td colspan="4" class="text-center text-muted py-4">No attributes recorded.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php if ($canEdit): ?>
<form class="p-4 border-top" id="lot-attributes-form" method="post" action="/lots/<?= e($id) ?>/attributes/save" hx-post="/lots/<?= e($id) ?>/attributes/save" hx-target="#lot-view-pane-attributes" hx-swap="innerHTML">
    <?= csrf_field() ?>
    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'lot-attributes-errors']) ?>
    <div class="row g-2 align-items-end">
        <div class="col-12 col-md-3">
            <label class="fw-semibold fs-12" for="lot-attributes-form-field-key">Attribute</label>
            <select class="form-select" id="lot-attributes-form-field-key" name="key">
                <?php foreach (LOT_ATTRIBUTE_KEYS as $key => $label): ?><option value="<?= e($key) ?>"<?= ($input['key'] ?? '') === $key ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                <option value="other"<?= ($input['key'] ?? '') === 'other' ? ' selected' : '' ?>>Other (name it)</option>
            </select>
        </div>
        <div class="col-6 col-md-2"><label class="fw-semibold fs-12" for="lot-attributes-form-field-other-key">Other name</label><input type="text" class="form-control" id="lot-attributes-form-field-other-key" name="other_key" value="<?= e($input['other_key'] ?? '') ?>" maxlength="40" /></div>
        <div class="col-6 col-md-2"><label class="fw-semibold fs-12" for="lot-attributes-form-field-value-num">Number</label><input type="number" step="any" class="form-control" id="lot-attributes-form-field-value-num" name="value_num" value="<?= e($input['value_num'] ?? '') ?>" /></div>
        <div class="col-6 col-md-2"><label class="fw-semibold fs-12" for="lot-attributes-form-field-value-text">Text</label><input type="text" class="form-control" id="lot-attributes-form-field-value-text" name="value_text" value="<?= e($input['value_text'] ?? '') ?>" maxlength="200" /></div>
        <div class="col-6 col-md-1"><label class="fw-semibold fs-12" for="lot-attributes-form-field-unit-code">Unit</label><input type="text" class="form-control" id="lot-attributes-form-field-unit-code" name="unit_code" value="<?= e($input['unit_code'] ?? '') ?>" maxlength="20" /></div>
        <div class="col-12 col-md-2"><button type="submit" class="btn btn-primary w-100" id="lot-attributes-form-save-btn"><i class="feather-plus me-1"></i>Record</button></div>
    </div>
</form>
<?php endif; ?>
