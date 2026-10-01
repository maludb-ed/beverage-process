<?php /** @var int|string $n  @var array $row  @var array $vessels  @var array $rowErrors  @var string $prefix  @var string $base  @var string $kind ('split' outputs | 'blend' inputs)  @var array $batches */
$p = $prefix . '-' . $n;
$name = $base . '[' . $n . ']';
$rowErrors = $rowErrors ?? [];
$err = static fn(string $field) => isset($rowErrors[$field]) ? '<div class="invalid-feedback d-block">' . e($rowErrors[$field]) . '</div>' : '';
$inv = static fn(string $field) => isset($rowErrors[$field]) ? ' is-invalid' : '';
$isBlend = $kind === 'blend';
$field = $isBlend ? 'batch_id' : 'vessel_id';
$options = $isBlend ? array_map(static fn($b) => $b['label'], $batches) : $vessels;
?>
<div class="batch-<?= e($kind) ?>-row border rounded p-3 mb-3" id="<?= e($p) ?>">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-md-8">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-<?= e(str_replace('_', '-', $field)) ?>"><?= $isBlend ? 'Batch' : 'Vessel' ?></label>
            <select class="form-select<?= $inv($field) ?>" id="<?= e($p) ?>-<?= e(str_replace('_', '-', $field)) ?>" name="<?= e($name) ?>[<?= e($field) ?>]">
                <option value=""><?= $isBlend ? 'Choose a batch' : 'Choose a vessel' ?></option>
                <?php foreach ($options as $value => $label): ?><option value="<?= e($value) ?>"<?= (int) ($row[$field] ?? 0) === (int) $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select><?= $err($field) ?>
        </div>
        <div class="col-8 col-md-3">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-volume-gal">Volume (<?= e(display_unit('L')) ?>)</label>
            <input type="number" step="any" min="0" class="form-control<?= $inv('volume_gal') ?>" id="<?= e($p) ?>-volume-gal" name="<?= e($name) ?>[volume_gal]" value="<?= e($row['volume_gal'] ?? '') ?>" /><?= $err('volume_gal') ?>
        </div>
        <div class="col-4 col-md-1 text-end">
            <button type="button" class="btn btn-sm btn-light-brand" id="<?= e($p) ?>-remove-btn" title="Remove" hx-on:click="this.closest('.batch-<?= e($kind) ?>-row').remove()"><i class="feather-trash-2"></i></button>
        </div>
    </div>
</div>
