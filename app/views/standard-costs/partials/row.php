<?php /** @var array $item  @var array $classes  @var bool $canEdit */
$id = (int) $item['id']; $url = '/standard-costs/new?item=' . rawurlencode($item['code']); $r = 'standard-cost-row-' . $id;
$kind = standard_cost_unit_kind($item['item_class']);
?>
<tr id="<?= e($r) ?>">
    <td id="<?= e($r) ?>-item"><?php if ($canEdit): ?><a <?= nav_attrs($url) ?>><?= status_dot($item['standard_cost_per_base'] === null ? 'secondary' : 'success') ?><span><?= e($item['name']) ?></span></a><?php else: ?><?= status_dot($item['standard_cost_per_base'] === null ? 'secondary' : 'success') ?><span><?= e($item['name']) ?></span><?php endif; ?> <small class="text-muted"><?= e($item['code']) ?></small></td>
    <td id="<?= e($r) ?>-class"><?= e($classes[$item['item_class']] ?? $item['item_class']) ?></td>
    <td id="<?= e($r) ?>-method"><?= e(STANDARD_COST_METHODS[$item['costing_method']] ?? $item['costing_method']) ?></td>
    <td id="<?= e($r) ?>-standard"><?php if ($item['standard_cost_per_base'] !== null): ?><span class="fw-semibold"><?= e(fmt_unit_cost($item['standard_cost_per_base'], $item['base_unit_code'], $kind)) ?></span> <small class="text-muted">($<?= e(number_format((float) $item['standard_cost_per_base'], 4)) ?> / <?= e($item['base_unit_code']) ?>)</small><?php else: ?><span class="text-muted">not set</span><?php endif; ?></td>
    <td id="<?= e($r) ?>-effective-from"><?= e(format_date($item['latest_effective_from'])) ?></td>
    <td id="<?= e($r) ?>-history"><?= e($item['history_count']) ?></td>
    <td id="<?= e($r) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canEdit): ?><?= row_edit_button($r . '-edit-btn', $url) ?><?php endif; ?>
        </div>
    </td>
</tr>
