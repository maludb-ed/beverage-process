<?php
/** @var int|string $n  @var array $line  @var array $catalog  @var array $lineErrors */
$p = 'receipt-form-line-' . $n;
$base = 'lines[' . $n . ']';
$lineErrors = $lineErrors ?? [];
$item = $catalog[(int) ($line['item_id'] ?? 0)] ?? null;
$err = static fn(string $name) => isset($lineErrors[$name]) ? '<div class="invalid-feedback d-block">' . e($lineErrors[$name]) . '</div>' : '';
$inv = static fn(string $name) => isset($lineErrors[$name]) ? ' is-invalid' : '';
$needsTag = receipt_needs_weigh_tag($item);
?>
<div class="receipt-line border rounded p-3 mb-3" id="<?= e($p) ?>">
    <input type="hidden" id="<?= e($p) ?>-po-line-id" name="<?= e($base) ?>[purchase_order_line_id]" value="<?= e($line['purchase_order_line_id'] ?? '') ?>" />
    <div class="row g-3 align-items-end">
        <div class="col-12 col-md-4">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-item-id">Item</label>
            <select class="form-select<?= $inv('item_id') ?>" id="<?= e($p) ?>-item-id" name="<?= e($base) ?>[item_id]"
                    hx-get="/receipts/line-row" hx-trigger="change" hx-target="#<?= e($p) ?>" hx-swap="outerHTML"
                    hx-include="#<?= e($p) ?>, #receipt-form-field-supplier-id, #receipt-form-field-received-at" hx-vals='{"n": "<?= e($n) ?>"}'>
                <option value="">Choose an item</option>
                <?php foreach ($catalog as $itemId => $option): ?>
                    <option value="<?= e($itemId) ?>"<?= (int) ($line['item_id'] ?? 0) === $itemId ? ' selected' : '' ?>><?= e($option['code'] . ' — ' . $option['name']) ?></option>
                <?php endforeach; ?>
            </select><?= $err('item_id') ?>
        </div>
        <div class="col-6 col-md-2">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-qty-received"><?= $needsTag ? 'Count (optional)' : 'Quantity' ?></label>
            <input type="number" step="any" min="0" class="form-control<?= $inv('qty_received') ?>" id="<?= e($p) ?>-qty-received" name="<?= e($base) ?>[qty_received]" value="<?= e($line['qty_received'] ?? '') ?>" /><?= $err('qty_received') ?>
        </div>
        <div class="col-6 col-md-2">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-purchase-unit-code">Unit</label>
            <select class="form-select<?= $inv('purchase_unit_code') ?>" id="<?= e($p) ?>-purchase-unit-code" name="<?= e($base) ?>[purchase_unit_code]">
                <?php foreach (($item['units'] ?? []) as $code => $unit): ?>
                    <option value="<?= e($code) ?>"<?= ($line['purchase_unit_code'] ?? '') === (string) $code ? ' selected' : '' ?>><?= e($unit['label']) ?></option>
                <?php endforeach; ?>
            </select><?= $err('purchase_unit_code') ?>
        </div>
        <div class="col-6 col-md-2">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-unit-price">Price per unit</label>
            <div class="input-group"><div class="input-group-text">$</div>
                <input type="number" step="any" min="0" class="form-control<?= $inv('unit_price') ?>" id="<?= e($p) ?>-unit-price" name="<?= e($base) ?>[unit_price]" value="<?= e($line['unit_price'] ?? '') ?>" /></div><?= $err('unit_price') ?>
        </div>
        <div class="col-6 col-md-2">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-expires-on">Expires</label>
            <input type="date" class="form-control<?= $inv('expires_on') ?>" id="<?= e($p) ?>-expires-on" name="<?= e($base) ?>[expires_on]" value="<?= e($line['expires_on'] ?? '') ?>" /><?= $err('expires_on') ?>
        </div>
        <div class="col-6 col-md-3">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-supplier-lot-number">Supplier lot</label>
            <input type="text" class="form-control" id="<?= e($p) ?>-supplier-lot-number" name="<?= e($base) ?>[supplier_lot_number]" value="<?= e($line['supplier_lot_number'] ?? '') ?>" maxlength="80" />
        </div>
        <div class="col-6 col-md-3">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-discrepancy-kind">Discrepancy</label>
            <select class="form-select" id="<?= e($p) ?>-discrepancy-kind" name="<?= e($base) ?>[discrepancy_kind]">
                <?php foreach (DISCREPANCY_KINDS as $value => $label): ?><option value="<?= e($value) ?>"<?= ($line['discrepancy_kind'] ?? 'none') === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-discrepancy-note">Discrepancy note</label>
            <input type="text" class="form-control" id="<?= e($p) ?>-discrepancy-note" name="<?= e($base) ?>[discrepancy_note]" value="<?= e($line['discrepancy_note'] ?? '') ?>" maxlength="200" />
        </div>
        <div class="col-12 col-md-3">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-notes">Notes</label>
            <input type="text" class="form-control" id="<?= e($p) ?>-notes" name="<?= e($base) ?>[notes]" value="<?= e($line['notes'] ?? '') ?>" maxlength="200" />
        </div>
    </div>
    <?php if ($needsTag): ?>
        <?= view('receipts/partials/weigh-tag.php', ['p' => $p, 'base' => $base, 'tag' => $line['weigh_tag'] ?? [], 'lineErrors' => $lineErrors]) ?>
    <?php endif; ?>
    <div class="text-end mt-2">
        <button type="button" class="btn btn-sm btn-light-brand" id="<?= e($p) ?>-remove-btn" hx-on:click="this.closest('.receipt-line').remove()"><i class="feather-trash-2 me-1"></i>Remove line</button>
    </div>
</div>
