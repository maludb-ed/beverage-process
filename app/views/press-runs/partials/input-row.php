<?php /** @var int|string $n  @var array $row  @var array $fruitLots  @var array $rowErrors */
$p = 'press-run-form-input-row-' . $n;
$base = 'inputs[' . $n . ']';
$rowErrors = $rowErrors ?? [];
$err = static fn(string $name) => isset($rowErrors[$name]) ? '<div class="invalid-feedback d-block">' . e($rowErrors[$name]) . '</div>' : '';
$inv = static fn(string $name) => isset($rowErrors[$name]) ? ' is-invalid' : '';
?>
<div class="press-run-input border rounded p-3 mb-3" id="<?= e($p) ?>">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-md-8">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-lot-id">Fruit lot</label>
            <select class="form-select<?= $inv('lot_id') ?>" id="<?= e($p) ?>-lot-id" name="<?= e($base) ?>[lot_id]">
                <option value=""><?= $fruitLots === [] ? 'No released fruit lots with stock' : 'Choose a fruit lot' ?></option>
                <?php foreach ($fruitLots as $lotId => $lot): ?>
                    <option value="<?= e($lotId) ?>"<?= (int) ($row['lot_id'] ?? 0) === (int) $lotId ? ' selected' : '' ?>><?= e($lot['label']) ?></option>
                <?php endforeach; ?>
            </select><?= $err('lot_id') ?>
        </div>
        <div class="col-8 col-md-3">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-qty-lb">Weight (<?= e(display_unit('kg', 'fruit')) ?>)</label>
            <input type="number" step="any" min="0" class="form-control<?= $inv('qty_lb') ?>" id="<?= e($p) ?>-qty-lb" name="<?= e($base) ?>[qty_lb]" value="<?= e($row['qty_lb'] ?? '') ?>" /><?= $err('qty_lb') ?>
        </div>
        <div class="col-4 col-md-1 text-end">
            <button type="button" class="btn btn-sm btn-light-brand" id="<?= e($p) ?>-remove-btn" title="Remove" hx-on:click="this.closest('.press-run-input').remove()"><i class="feather-trash-2"></i></button>
        </div>
    </div>
</div>
