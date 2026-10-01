<?php
/** @var int|string $n  @var array $line  @var array $catalog  @var array $lineErrors */
$p = 'packaging-config-form-bom-' . $n;
$lineErrors = $lineErrors ?? [];
$item = $catalog[(int) ($line['item_id'] ?? 0)] ?? null;
$field = static fn(string $name) => 'bom[' . $n . '][' . $name . ']';
$err = static fn(string $name) => isset($lineErrors[$name]) ? '<div class="invalid-feedback d-block">' . e($lineErrors[$name]) . '</div>' : '';
$inv = static fn(string $name) => isset($lineErrors[$name]) ? ' is-invalid' : '';
?>
<div class="bom-line border rounded p-3 mb-3" id="<?= e($p) ?>">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-md-7">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-item">Item</label>
            <select class="form-select<?= $inv('item_id') ?>" id="<?= e($p) ?>-item" name="<?= e($field('item_id')) ?>"
                    hx-get="/packaging-configs/bom-row" hx-trigger="change" hx-target="#<?= e($p) ?>" hx-swap="outerHTML" hx-include="#<?= e($p) ?>" hx-vals='{"n": "<?= e($n) ?>"}'>
                <option value="">Choose an item</option>
                <?php foreach ($catalog as $itemId => $option): ?>
                    <option value="<?= e($itemId) ?>"<?= (int) ($line['item_id'] ?? 0) === $itemId ? ' selected' : '' ?>><?= e($option['code'] . ' — ' . $option['name']) ?></option>
                <?php endforeach; ?>
            </select><?= $err('item_id') ?>
        </div>
        <div class="col-8 col-md-3">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-qty">Quantity per unit</label>
            <div class="input-group">
                <input type="number" step="any" min="0" class="form-control<?= $inv('qty') ?>" id="<?= e($p) ?>-qty" name="<?= e($field('qty')) ?>" value="<?= e($line['qty'] ?? '') ?>" />
                <div class="input-group-text" id="<?= e($p) ?>-unit"><?= e($item === null ? '' : display_unit($item['base_unit_code'])) ?></div>
            </div><?= $err('qty') ?>
        </div>
        <div class="col-4 col-md-2 text-end">
            <button type="button" class="btn btn-sm btn-light-brand" id="<?= e($p) ?>-remove-btn" hx-on:click="this.closest('.bom-line').remove()"><i class="feather-trash-2 me-1"></i>Remove</button>
        </div>
    </div>
</div>
