<?php /** @var int|string $n  @var array $row  @var array $juiceLots  @var array $rowErrors */
$p = 'batch-form-juice-row-' . $n;
$base = 'juice[' . $n . ']';
$rowErrors = $rowErrors ?? [];
$err = static fn(string $name) => isset($rowErrors[$name]) ? '<div class="invalid-feedback d-block">' . e($rowErrors[$name]) . '</div>' : '';
$inv = static fn(string $name) => isset($rowErrors[$name]) ? ' is-invalid' : '';
?>
<div class="batch-juice border rounded p-3 mb-3" id="<?= e($p) ?>">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-md-8">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-lot-id">Juice lot</label>
            <select class="form-select<?= $inv('lot_id') ?>" id="<?= e($p) ?>-lot-id" name="<?= e($base) ?>[lot_id]">
                <option value=""><?= $juiceLots === [] ? 'No released juice lots' : 'Choose a juice lot' ?></option>
                <?php foreach ($juiceLots as $lotId => $lot): ?>
                    <option value="<?= e($lotId) ?>"<?= (int) ($row['lot_id'] ?? 0) === (int) $lotId ? ' selected' : '' ?>><?= e($lot['label']) ?></option>
                <?php endforeach; ?>
            </select><?= $err('lot_id') ?>
        </div>
        <div class="col-8 col-md-3">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-volume-gal">Volume (<?= e(display_unit('L')) ?>)</label>
            <input type="number" step="any" min="0" class="form-control<?= $inv('volume_gal') ?>" id="<?= e($p) ?>-volume-gal" name="<?= e($base) ?>[volume_gal]" value="<?= e($row['volume_gal'] ?? '') ?>" /><?= $err('volume_gal') ?>
        </div>
        <div class="col-4 col-md-1 text-end">
            <button type="button" class="btn btn-sm btn-light-brand" id="<?= e($p) ?>-remove-btn" title="Remove" hx-on:click="this.closest('.batch-juice').remove()"><i class="feather-trash-2"></i></button>
        </div>
    </div>
</div>
