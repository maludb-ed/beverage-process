<?php
/** @var int|string $n  @var array $line  @var array $catalog  @var array $lineErrors  @var bool $canPrice  @var ?array $availability */
$p = 'order-form-line-' . $n;
$lineErrors = $lineErrors ?? [];
$orderId = $orderId ?? null;
$format = $catalog[(int) ($line['packaging_configuration_id'] ?? 0)] ?? null;
$field = static fn(string $name) => 'lines[' . $n . '][' . $name . ']';
$err = static fn(string $name) => isset($lineErrors[$name]) ? '<div class="invalid-feedback d-block">' . e($lineErrors[$name]) . '</div>' : '';
$inv = static fn(string $name) => isset($lineErrors[$name]) ? ' is-invalid' : '';
$refresh = 'hx-get="/orders/line-row" hx-target="#' . e($p) . '" hx-swap="outerHTML" hx-include="#' . e($p) . '"';
$vals = static fn(string $changed) => "hx-vals='" . e(json_encode(['n' => (string) $n, 'changed' => $changed, 'order_id' => $orderId])) . "'";
?>
<div class="order-line border rounded p-3 mb-3" id="<?= e($p) ?>">
    <?php if (!empty($line['id'])): ?><input type="hidden" name="<?= e($field('id')) ?>" value="<?= e($line['id']) ?>" /><?php endif; ?>
    <div class="row g-3 align-items-end">
        <div class="col-12 col-md-5">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-format">Product and format</label>
            <select class="form-select<?= $inv('packaging_configuration_id') ?>" id="<?= e($p) ?>-format" name="<?= e($field('packaging_configuration_id')) ?>"
                    <?= $refresh ?> hx-trigger="change" <?= $vals('format') ?>>
                <option value="">Choose a format</option>
                <?php $group = null; foreach ($catalog as $configId => $option): ?>
                    <?php if ($group !== $option['product_name']): ?><?= $group !== null ? '</optgroup>' : '' ?><optgroup label="<?= e($option['product_name']) ?>"><?php $group = $option['product_name']; endif; ?>
                    <option value="<?= e($configId) ?>"<?= (int) ($line['packaging_configuration_id'] ?? 0) === $configId ? ' selected' : '' ?>><?= e($option['name']) ?></option>
                <?php endforeach; ?><?= $group !== null ? '</optgroup>' : '' ?>
            </select><?= $err('packaging_configuration_id') ?>
        </div>
        <div class="col-6 col-md-2">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-units">Units</label>
            <input type="number" step="1" min="1" class="form-control<?= $inv('units_ordered') ?>" id="<?= e($p) ?>-units" name="<?= e($field('units_ordered')) ?>" value="<?= e($line['units_ordered'] ?? '') ?>" /><?= $err('units_ordered') ?>
        </div>
        <?php if ($canPrice): ?>
        <div class="col-6 col-md-2">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-price">Price per unit</label>
            <div class="input-group"><div class="input-group-text">$</div>
                <input type="number" step="0.01" min="0" class="form-control<?= $inv('unit_price') ?>" id="<?= e($p) ?>-price" name="<?= e($field('unit_price')) ?>" value="<?= e($line['unit_price'] ?? '') ?>" /></div><?= $err('unit_price') ?>
        </div>
        <?php endif; ?>
        <div class="col-12 col-md-3">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-notes">Note</label>
            <input type="text" maxlength="500" class="form-control" id="<?= e($p) ?>-notes" name="<?= e($field('notes')) ?>" value="<?= e($line['notes'] ?? '') ?>" />
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mt-2">
        <div class="fs-12 text-muted" id="<?= e($p) ?>-availability">
            <?php if ($format !== null && $availability !== null): ?>
                <?php $free = (int) $availability['units_available'] - (int) $availability['units_promised']; ?>
                <?= e(number_format((int) $availability['units_available'])) ?> on hand, <?= e(number_format((int) $availability['units_promised'])) ?> promised to other orders
                (<span class="<?= $free < (int) ($line['units_ordered'] ?: 0) ? 'text-warning fw-semibold' : '' ?>"><?= e(number_format($free)) ?> free</span>);
                <?= e(fmt_qty($availability['bulk_volume_l'], 'L')) ?> of <?= e($format['product_name']) ?> in bulk.
                <?php if ($format['units_per_case']): ?><?= e($format['units_per_case']) ?> per case.<?php endif; ?>
            <?php elseif ($format !== null): ?>
                <a href="#" class="text-muted" id="<?= e($p) ?>-check-btn" <?= $refresh ?> hx-trigger="click" <?= $vals('check') ?>>Check stock and bulk</a>
            <?php endif; ?>
        </div>
        <button type="button" class="btn btn-sm btn-light-brand" id="<?= e($p) ?>-remove-btn" hx-on:click="this.closest('.order-line').remove()"><i class="feather-trash-2 me-1"></i>Remove line</button>
    </div>
</div>
