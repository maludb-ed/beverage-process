<?php /** @var string $p  @var string $base  @var array $lots  @var mixed $selected  @var ?array $item  @var string $error */
$unit = $item !== null ? display_unit($item['base_unit_code'], $item['kind']) : '';
?>
<label class="fw-semibold fs-12" for="<?= e($p) ?>-lot">Lot</label>
<select class="form-select<?= $error !== '' ? ' is-invalid' : '' ?>" id="<?= e($p) ?>-lot" name="<?= e($base) ?>[lot_id]">
    <option value=""><?= $item === null ? 'Choose an item first' : ($lots === [] ? 'This item has no lots' : 'Choose a lot') ?></option>
    <?php foreach ($lots as $lot): ?>
        <option value="<?= e($lot['lot_id']) ?>"<?= (int) $selected === (int) $lot['lot_id'] ? ' selected' : '' ?>><?= e($lot['lot_number'] . ' · ' . fmt_qty($lot['qty_here'], $lot['base_unit_code'], 1, inventory_unit_kind($lot['item_class'])) . ' here · ' . humanize($lot['quality_status'])) ?></option>
    <?php endforeach; ?>
</select><?php if ($error !== ''): ?><div class="invalid-feedback d-block"><?= e($error) ?></div><?php endif; ?>
<?php if ($unit !== ''): ?><div class="fs-11 text-muted mt-1" id="<?= e($p) ?>-unit-hint">Enter the change in <?= e($unit) ?>; use a minus sign to remove stock. Cost is per <?= e($item['base_unit_code']) ?>.</div><?php endif; ?>
