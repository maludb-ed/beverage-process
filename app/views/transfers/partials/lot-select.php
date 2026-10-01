<?php /** @var string $p  @var string $base  @var array $lots  @var mixed $selected  @var ?array $item  @var string $error */
$unit = $item !== null ? display_unit($item['base_unit_code'], $item['kind']) : '';
?>
<label class="fw-semibold fs-12" for="<?= e($p) ?>-lot">Lot</label>
<select class="form-select<?= $error !== '' ? ' is-invalid' : '' ?>" id="<?= e($p) ?>-lot" name="<?= e($base) ?>[lot_id]">
    <option value=""><?= $item === null ? 'Choose an item first' : ($lots === [] ? 'No released lots at this location' : 'Choose a lot') ?></option>
    <?php foreach ($lots as $lot): ?>
        <option value="<?= e($lot['lot_id']) ?>"<?= (int) $selected === (int) $lot['lot_id'] ? ' selected' : '' ?>><?= e($lot['lot_number'] . ' · ' . fmt_qty($lot['qty_available'], $lot['base_unit_code'], 1, inventory_unit_kind($lot['item_class'])) . ' · ' . ($lot['expires_on'] ? format_date($lot['expires_on']) : 'no expiry')) ?></option>
    <?php endforeach; ?>
</select><?php if ($error !== ''): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endif; ?>
<?php if ($unit !== ''): ?><div class="fs-11 text-muted mt-1" id="<?= e($p) ?>-unit-hint">Enter the quantity in <?= e($unit) ?>.</div><?php endif; ?>
