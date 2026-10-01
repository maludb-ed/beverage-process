<?php /** @var array $lots  @var mixed $selected */ ?>
<label class="fw-semibold fs-12" for="count-view-add-line-lot">Lot</label>
<select class="form-select" id="count-view-add-line-lot" name="lot_id">
    <option value=""><?= $lots === [] ? 'Choose an item first' : 'Choose a lot' ?></option>
    <?php foreach ($lots as $lot): ?>
        <option value="<?= e($lot['lot_id']) ?>"<?= (int) $selected === (int) $lot['lot_id'] ? ' selected' : '' ?>><?= e($lot['lot_number'] . ' · ' . fmt_qty($lot['qty_here'], $lot['base_unit_code'], 1, inventory_unit_kind($lot['item_class'])) . ' here · ' . humanize($lot['quality_status'])) ?></option>
    <?php endforeach; ?>
</select>
