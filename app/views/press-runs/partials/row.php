<?php /** @var array $run  @var bool $canEdit */
$id = (int) $run['id'];
$viewUrl = '/press-runs/' . $id;
$r = 'press-run-row-' . $id;
$fruitKg = $run['fruit_kg_total'] ?? $run['draft_fruit_kg'];
$juiceL = $run['juice_l_total'] ?? $run['draft_juice_l'];
$yield = batches_press_yield($run['yield_l_per_kg']);
?>
<tr id="<?= e($r) ?>">
    <td id="<?= e($r) ?>-number"><a <?= nav_attrs($viewUrl) ?>><?= status_dot(status_color($run['status'])) ?><span><?= e($run['number']) ?></span></a></td>
    <td id="<?= e($r) ?>-run-on"><?= e(format_date($run['run_on'])) ?></td>
    <td id="<?= e($r) ?>-press"><?= e($run['press_name'] ?? '') ?></td>
    <td id="<?= e($r) ?>-fruit"><?= fmt_qty_html($fruitKg, 'kg', 0, 'fruit') ?></td>
    <td id="<?= e($r) ?>-juice"><?= fmt_qty_html($juiceL, 'L', 1) ?></td>
    <td id="<?= e($r) ?>-yield"><?php if ($yield['gal_per_ton'] !== null): ?><?= e(number_format($yield['gal_per_ton'], 0)) ?> gal/ton <small class="text-muted"><?= e(number_format($yield['gal_per_bushel'], 2)) ?> gal/bu</small><?php endif; ?></td>
    <td id="<?= e($r) ?>-status"><?= status_badge($run['status']) ?></td>
    <td id="<?= e($r) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canEdit && $run['status'] === 'draft'): ?><?= row_edit_button($r . '-edit-btn', $viewUrl . '/edit') ?><?php endif; ?>
        </div>
    </td>
</tr>
