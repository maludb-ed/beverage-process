<?php /** @var int|string $n  @var array $line  @var array $itemOptions  @var array $lineErrors */
$p = 'transfer-form-line-' . $n;
$base = 'lines[' . $n . ']';
$lineErrors = $lineErrors ?? [];
$err = static fn(string $name) => isset($lineErrors[$name]) ? '<div class="invalid-feedback d-block">' . e($lineErrors[$name]) . '</div>' : '';
$inv = static fn(string $name) => isset($lineErrors[$name]) ? ' is-invalid' : '';
?>
<div class="transfer-line border rounded p-3 mb-3" id="<?= e($p) ?>">
    <div class="row g-3 align-items-start">
        <div class="col-12 col-md-4">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-item">Item</label>
            <select class="form-select<?= $inv('item_id') ?>" id="<?= e($p) ?>-item" name="<?= e($base) ?>[item_id]"
                    hx-get="/transfers/lots" hx-trigger="change" hx-target="#<?= e($p) ?>-lot-wrap" hx-swap="innerHTML"
                    hx-include="#transfer-form-field-from-location" hx-vals='{"n": "<?= e($n) ?>"}'>
                <option value="">Choose an item</option>
                <?php foreach ($itemOptions as $itemId => $label): ?>
                    <option value="<?= e($itemId) ?>"<?= (int) ($line['item_id'] ?? 0) === (int) $itemId ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select><?= $err('item_id') ?>
        </div>
        <div class="col-12 col-md-5" id="<?= e($p) ?>-lot-wrap">
            <?= view('transfers/partials/lot-select.php', ['p' => $p, 'base' => $base, 'lots' => $line['lots'] ?? [], 'selected' => $line['lot_id'] ?? '', 'item' => $line['item_facts'] ?? null, 'error' => $lineErrors['lot_id'] ?? '']) ?>
        </div>
        <div class="col-12 col-md-3">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-qty">Quantity</label>
            <input type="number" step="any" min="0" class="form-control<?= $inv('qty') ?>" id="<?= e($p) ?>-qty" name="<?= e($base) ?>[qty]" value="<?= e($line['qty'] ?? '') ?>" /><?= $err('qty') ?>
        </div>
    </div>
    <div class="text-end mt-2">
        <button type="button" class="btn btn-sm btn-light-brand" id="<?= e($p) ?>-remove-btn" hx-on:click="this.closest('.transfer-line').remove()"><i class="feather-trash-2 me-1"></i>Remove line</button>
    </div>
</div>
