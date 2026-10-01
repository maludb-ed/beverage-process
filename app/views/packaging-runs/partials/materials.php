<?php /** @var array $materials  @var array $materialErrors  @var ?array $configuration */
$materialErrors = $materialErrors ?? [];
?>
<?php if ($configuration === null): ?>
    <p class="text-muted mb-0" id="packaging-run-form-materials-empty">Choose a package to load its bill of materials.</p>
<?php elseif ($materials === []): ?>
    <p class="text-muted mb-0" id="packaging-run-form-materials-no-bom">This package has no bill of materials.</p>
<?php else: foreach ($materials as $itemId => $row): $mp = 'packaging-run-form-material-' . $itemId; ?>
    <div class="border rounded p-3 mb-3" id="<?= e($mp) ?>">
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <div class="fw-semibold fs-12">Item</div>
                <div id="<?= e($mp) ?>-item"><?= e($row['item_code']) ?> <small class="text-muted"><?= e($row['item_name']) ?></small></div>
            </div>
            <div class="col-6 col-md-2">
                <div class="fw-semibold fs-12">Mode</div>
                <div id="<?= e($mp) ?>-mode"><?= badge(humanize($row['consumption_mode']), $row['consumption_mode'] === 'explicit' ? 'info' : 'secondary') ?></div>
            </div>
            <div class="col-6 col-md-2">
                <label class="fw-semibold fs-12" for="<?= e($mp) ?>-qty">Quantity (<?= e($row['unit']) ?>)</label>
                <input type="number" step="any" min="0" class="form-control<?= isset($materialErrors[$itemId]) ? ' is-invalid' : '' ?>" id="<?= e($mp) ?>-qty" name="materials[<?= e($itemId) ?>][qty]" value="<?= e($row['qty']) ?>" />
            </div>
            <div class="col-12 col-md-4">
                <label class="fw-semibold fs-12" for="<?= e($mp) ?>-lot-id">Lot</label>
                <select class="form-select<?= isset($materialErrors[$itemId]) ? ' is-invalid' : '' ?>" id="<?= e($mp) ?>-lot-id" name="materials[<?= e($itemId) ?>][lot_id]">
                    <option value=""><?= e($row['consumption_mode'] === 'explicit' ? 'Choose a lot' : 'Backflush (earliest expiry first)') ?></option>
                    <?php foreach ($row['lots'] as $lotId => $label): ?><option value="<?= e($lotId) ?>"<?= (int) $row['lot_id'] === (int) $lotId ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php if (isset($materialErrors[$itemId])): ?><div class="invalid-feedback d-block" id="<?= e($mp) ?>-error"><?= e($materialErrors[$itemId]) ?></div><?php endif; ?>
    </div>
<?php endforeach; endif; ?>
