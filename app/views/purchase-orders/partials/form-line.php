<?php
/** @var int|string $n  @var array $line  @var array $catalog  @var array $lineErrors */
$p = 'purchase-order-form-line-' . $n;
$lineErrors = $lineErrors ?? [];
$item = $catalog[(int) ($line['item_id'] ?? 0)] ?? null;
$field = static fn(string $name) => 'lines[' . $n . '][' . $name . ']';
$err = static fn(string $name) => isset($lineErrors[$name]) ? '<div class="invalid-feedback d-block">' . e($lineErrors[$name]) . '</div>' : '';
$inv = static fn(string $name) => isset($lineErrors[$name]) ? ' is-invalid' : '';
?>
<div class="po-line border rounded p-3 mb-3" id="<?= e($p) ?>">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-md-4">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-item-id">Item</label>
            <select class="form-select<?= $inv('item_id') ?>" id="<?= e($p) ?>-item-id" name="<?= e($field('item_id')) ?>"
                    hx-get="/purchase-orders/line-row" hx-trigger="change" hx-target="#<?= e($p) ?>" hx-swap="outerHTML"
                    hx-include="#<?= e($p) ?>, #purchase-order-form-field-supplier-id" hx-vals='{"n": "<?= e($n) ?>"}'>
                <option value="">Choose an item</option>
                <?php foreach ($catalog as $itemId => $option): ?>
                    <option value="<?= e($itemId) ?>"<?= (int) ($line['item_id'] ?? 0) === $itemId ? ' selected' : '' ?>><?= e($option['code'] . ' — ' . $option['name']) ?></option>
                <?php endforeach; ?>
            </select><?= $err('item_id') ?>
        </div>
        <div class="col-6 col-md-2">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-qty-ordered">Quantity</label>
            <input type="number" step="any" min="0" class="form-control<?= $inv('qty_ordered') ?>" id="<?= e($p) ?>-qty-ordered" name="<?= e($field('qty_ordered')) ?>" value="<?= e($line['qty_ordered'] ?? '') ?>" /><?= $err('qty_ordered') ?>
        </div>
        <div class="col-6 col-md-2">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-purchase-unit-code">Unit</label>
            <select class="form-select<?= $inv('purchase_unit_code') ?>" id="<?= e($p) ?>-purchase-unit-code" name="<?= e($field('purchase_unit_code')) ?>">
                <?php foreach (($item['units'] ?? []) as $code => $unit): ?>
                    <option value="<?= e($code) ?>"<?= ($line['purchase_unit_code'] ?? '') === (string) $code ? ' selected' : '' ?>><?= e($unit['label']) ?></option>
                <?php endforeach; ?>
            </select><?= $err('purchase_unit_code') ?>
        </div>
        <div class="col-6 col-md-2">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-unit-price">Price per unit</label>
            <div class="input-group"><div class="input-group-text">$</div>
                <input type="number" step="any" min="0" class="form-control<?= $inv('unit_price') ?>" id="<?= e($p) ?>-unit-price" name="<?= e($field('unit_price')) ?>" value="<?= e($line['unit_price'] ?? '') ?>" /></div><?= $err('unit_price') ?>
        </div>
        <div class="col-6 col-md-2">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-expected-on">Expected</label>
            <input type="date" class="form-control<?= $inv('expected_on') ?>" id="<?= e($p) ?>-expected-on" name="<?= e($field('expected_on')) ?>" value="<?= e($line['expected_on'] ?? '') ?>" /><?= $err('expected_on') ?>
        </div>
    </div>
    <div class="text-end mt-2">
        <button type="button" class="btn btn-sm btn-light-brand" id="<?= e($p) ?>-remove-btn" hx-on:click="this.closest('.po-line').remove()"><i class="feather-trash-2 me-1"></i>Remove line</button>
    </div>
</div>
