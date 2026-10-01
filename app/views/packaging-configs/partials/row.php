<?php /** @var array $config  @var bool $canEdit */ $id = (int) $config['id']; $editUrl = '/packaging-configs/' . $id . '/edit'; $r = 'packaging-config-row-' . $id; ?>
<tr id="<?= e($r) ?>">
    <td id="<?= e($r) ?>-name"><a <?= nav_attrs($editUrl) ?>><?= status_dot($config['active'] ? 'success' : 'secondary') ?><span><?= e($config['name']) ?></span></a></td>
    <td id="<?= e($r) ?>-product"><?= e($config['product_name']) ?></td>
    <td id="<?= e($r) ?>-item"><?= e($config['item_name']) ?> <small class="text-muted"><?= e($config['item_code']) ?></small></td>
    <td id="<?= e($r) ?>-kind"><?= e(PACKAGE_KINDS[$config['package_kind']] ?? $config['package_kind']) ?></td>
    <td id="<?= e($r) ?>-fill"><?= fmt_qty_html($config['fill_volume_l'], 'L', 3) ?></td>
    <td id="<?= e($r) ?>-units"><?= e($config['units_per_case']) ?></td>
    <td id="<?= e($r) ?>-loss"><?= e(number_format((float) $config['expected_loss_pct'], 2)) ?>%</td>
    <td id="<?= e($r) ?>-bom"><?= e($config['bom_count']) ?></td>
    <td id="<?= e($r) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canEdit): ?><?= row_edit_button($r . '-edit-btn', $editUrl) ?><?php endif; ?>
        </div>
    </td>
</tr>
