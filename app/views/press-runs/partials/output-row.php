<?php /** @var int|string $n  @var array $row  @var array $outputItems  @var array $vessels  @var array $locations  @var array $rowErrors */
$p = 'press-run-form-output-row-' . $n;
$base = 'outputs[' . $n . ']';
$rowErrors = $rowErrors ?? [];
$kind = ($row['kind'] ?? 'juice') === 'pomace' ? 'pomace' : 'juice';
$items = $outputItems[$kind];
$item = $items[(int) ($row['item_id'] ?? 0)] ?? (count($items) === 1 ? reset($items) : null);
$unit = $kind === 'juice' ? display_unit('L') : display_unit($item['base_unit_code'] ?? 'kg');
$err = static fn(string $name) => isset($rowErrors[$name]) ? '<div class="invalid-feedback d-block">' . e($rowErrors[$name]) . '</div>' : '';
$inv = static fn(string $name) => isset($rowErrors[$name]) ? ' is-invalid' : '';
?>
<div class="press-run-output border rounded p-3 mb-3" id="<?= e($p) ?>">
    <div class="row g-3 align-items-end">
        <div class="col-6 col-md-2">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-kind">Kind</label>
            <select class="form-select" id="<?= e($p) ?>-kind" name="<?= e($base) ?>[kind]"
                    hx-get="/press-runs/output-row" hx-trigger="change" hx-target="#<?= e($p) ?>" hx-swap="outerHTML" hx-include="#<?= e($p) ?>" hx-vals='{"n": "<?= e($n) ?>"}'>
                <?php foreach (PRESS_RUN_OUTPUT_KINDS as $value => $label): ?><option value="<?= e($value) ?>"<?= $kind === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-3">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-item-id">Item</label>
            <select class="form-select<?= $inv('item_id') ?>" id="<?= e($p) ?>-item-id" name="<?= e($base) ?>[item_id]">
                <?php if (count($items) !== 1): ?><option value=""><?= $items === [] ? ($kind === 'juice' ? 'No juice items' : 'No co-product items') : 'Choose an item' ?></option><?php endif; ?>
                <?php foreach ($items as $itemId => $option): ?>
                    <option value="<?= e($itemId) ?>"<?= (int) ($item['id'] ?? 0) === (int) $itemId ? ' selected' : '' ?>><?= e($option['code'] . ' — ' . $option['name']) ?></option>
                <?php endforeach; ?>
            </select><?= $err('item_id') ?>
        </div>
        <div class="col-6 col-md-2">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-qty">Quantity (<?= e($unit) ?>)</label>
            <input type="number" step="any" min="0" class="form-control<?= $inv('qty') ?>" id="<?= e($p) ?>-qty" name="<?= e($base) ?>[qty]" value="<?= e($row['qty'] ?? '') ?>" /><?= $err('qty') ?>
        </div>
        <?php if ($kind === 'juice'): ?>
        <div class="col-6 col-md-2">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-brix">Brix</label>
            <input type="number" step="0.1" min="0" max="40" class="form-control<?= $inv('brix') ?>" id="<?= e($p) ?>-brix" name="<?= e($base) ?>[brix]" value="<?= e($row['brix'] ?? '') ?>" /><?= $err('brix') ?>
        </div>
        <div class="col-10 col-md-2">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-vessel-id">Vessel</label>
            <select class="form-select<?= $inv('vessel_id') ?>" id="<?= e($p) ?>-vessel-id" name="<?= e($base) ?>[vessel_id]">
                <option value="">Choose a vessel</option>
                <?php foreach ($vessels as $vesselId => $label): ?><option value="<?= e($vesselId) ?>"<?= (int) ($row['vessel_id'] ?? 0) === (int) $vesselId ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select><?= $err('vessel_id') ?>
        </div>
        <?php else: ?>
        <div class="col-10 col-md-4">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-location-id">Location</label>
            <select class="form-select<?= $inv('location_id') ?>" id="<?= e($p) ?>-location-id" name="<?= e($base) ?>[location_id]">
                <option value="">Choose a location</option>
                <?php foreach ($locations as $locationId => $label): ?><option value="<?= e($locationId) ?>"<?= (int) ($row['location_id'] ?? 0) === (int) $locationId ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select><?= $err('location_id') ?>
        </div>
        <?php endif; ?>
        <div class="col-2 col-md-1 text-end">
            <button type="button" class="btn btn-sm btn-light-brand" id="<?= e($p) ?>-remove-btn" title="Remove" hx-on:click="this.closest('.press-run-output').remove()"><i class="feather-trash-2"></i></button>
        </div>
    </div>
</div>
