<?php /** @var string $n  @var array $line  @var array $options  @var string $direction  @var array $lineErrors */
$p = 'removal-form-line-' . $n;
$base = 'lines[' . $n . ']';
$lineErrors = $lineErrors ?? [];
$lotId = (int) ($line['lot_id'] ?? 0);
$lot = $options['lots'][$lotId] ?? null;
$isKeg = $lot !== null && $lot['package_kind'] === 'keg';
$kegs = $isKeg ? ($options['kegs'][$lotId] ?? []) : [];
$selectedKegs = array_map('intval', $line['keg_ids'] ?? []);
$err = static fn(string $name) => isset($lineErrors[$name]) ? '<div class="invalid-feedback d-block">' . e($lineErrors[$name]) . '</div>' : '';
$inv = static fn(string $name) => isset($lineErrors[$name]) ? ' is-invalid' : '';
$include = '#' . $p . ', #removal-form-field-direction, #removal-form-field-from-location-id, #removal-form-field-customer-id';
?>
<div class="removal-line border rounded p-3 mb-3" id="<?= e($p) ?>">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-md-6">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-lot-id">Finished lot</label>
            <select class="form-select<?= $inv('lot_id') ?>" id="<?= e($p) ?>-lot-id" name="<?= e($base) ?>[lot_id]"
                    hx-get="/removals/line-row" hx-trigger="change" hx-target="#<?= e($p) ?>" hx-swap="outerHTML" hx-include="<?= e($include) ?>" hx-vals='{"n": "<?= e($n) ?>"}'>
                <option value=""><?= e($options['lots'] === [] ? 'No finished lots available' : 'Choose a finished lot') ?></option>
                <?php foreach ($options['lots'] as $optionId => $option): ?>
                    <option value="<?= e($optionId) ?>"<?= $lotId === (int) $optionId ? ' selected' : '' ?>><?= e($option['lot_number'] . ' — ' . $option['product_name'] . ', ' . $option['package_name'] . ' (' . (int) $option['units_available'] . ($direction === 'in' ? ' out' : ' available') . ')') ?></option>
                <?php endforeach; ?>
            </select><?= $err('lot_id') ?>
        </div>
        <div class="col-6 col-md-2">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-units">Units</label>
            <input type="number" step="1" min="1"<?= $lot !== null ? ' max="' . e((int) $lot['units_available']) . '"' : '' ?> class="form-control<?= $inv('units') ?>" id="<?= e($p) ?>-units" name="<?= e($base) ?>[units]" value="<?= e($line['units'] ?? '') ?>" /><?= $err('units') ?>
        </div>
        <div class="col-6 col-md-4">
            <div class="fs-12 text-muted" id="<?= e($p) ?>-readback">
                <?php if ($lot !== null): ?>
                    <?= e(TAX_CLASS_LABELS[$lot['tax_class']] ?? humanize($lot['tax_class'])) ?> · <?= e(fmt_qty($lot['unit_volume_l'], 'L', 2)) ?> per unit<br />
                    <?= e((int) $lot['units_available']) ?> <?= $direction === 'in' ? 'still at the customer' : 'available here' ?>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($isKeg): ?>
            <div class="col-12">
                <label class="fw-semibold fs-12" for="<?= e($p) ?>-keg-ids">Kegs (one per unit)</label>
                <?php if ($kegs === []): ?>
                    <div class="text-warning fs-12" id="<?= e($p) ?>-keg-ids"><?= $direction === 'in' ? 'No kegs of this lot are recorded at the customer.' : 'No filled kegs are recorded for this lot.' ?></div>
                <?php else: ?>
                    <select multiple size="<?= e(min(6, max(2, count($kegs)))) ?>" class="form-select<?= $inv('keg_ids') ?>" id="<?= e($p) ?>-keg-ids" name="<?= e($base) ?>[keg_ids][]">
                        <?php foreach ($kegs as $kegId => $serial): ?>
                            <option value="<?= e($kegId) ?>"<?= in_array((int) $kegId, $selectedKegs, true) ? ' selected' : '' ?>><?= e($serial) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
                <?= $err('keg_ids') ?>
            </div>
        <?php endif; ?>
    </div>
    <div class="text-end mt-2">
        <button type="button" class="btn btn-sm btn-light-brand" id="<?= e($p) ?>-remove-btn" hx-on:click="const f = this.closest('form'); this.closest('.removal-line').remove(); f.dispatchEvent(new Event('change', {bubbles: true}))"><i class="feather-trash-2 me-1"></i>Remove line</button>
    </div>
</div>
