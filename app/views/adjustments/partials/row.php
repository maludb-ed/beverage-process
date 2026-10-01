<?php /** @var array $adjustment  @var bool $canEdit */
$id = (int) $adjustment['id'];
$viewUrl = '/adjustments/' . $id;
$net = (float) $adjustment['net_qty'];
?>
<tr id="adjustment-row-<?= e($id) ?>">
    <td id="adjustment-row-<?= e($id) ?>-number"><a <?= nav_attrs($viewUrl) ?>><?= status_dot(status_color($adjustment['status'])) ?><span><?= e($adjustment['number']) ?></span></a></td>
    <td id="adjustment-row-<?= e($id) ?>-location"><?= e($adjustment['location_name']) ?></td>
    <td id="adjustment-row-<?= e($id) ?>-reason"><?= e($adjustment['reason_code']) ?> <small class="text-muted"><?= e($adjustment['reason_name']) ?></small></td>
    <td id="adjustment-row-<?= e($id) ?>-lines"><?= e($adjustment['line_count']) ?></td>
    <td id="adjustment-row-<?= e($id) ?>-net-qty" class="fw-semibold <?= $net < 0 ? 'text-danger' : '' ?>"><?php if ((int) $adjustment['item_count'] === 1): ?><?= $net > 0 ? '+' : '' ?><?= fmt_qty_html($net, $adjustment['base_unit_code'], 1, inventory_unit_kind($adjustment['item_class'])) ?><?php elseif ((int) $adjustment['item_count'] > 1): ?><span class="text-muted fw-normal">Mixed items</span><?php endif; ?></td>
    <td id="adjustment-row-<?= e($id) ?>-adjusted"><?= e(format_date($adjustment['adjusted_at'])) ?></td>
    <td id="adjustment-row-<?= e($id) ?>-status"><?= status_badge($adjustment['status']) ?></td>
    <td id="adjustment-row-<?= e($id) ?>-created-by"><?= e($adjustment['created_by_name'] ?? '') ?></td>
    <td id="adjustment-row-<?= e($id) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canEdit && $adjustment['status'] === 'draft'): ?><?= row_edit_button('adjustment-row-' . $id . '-edit-btn', $viewUrl . '/edit') ?><?php endif; ?>
        </div>
    </td>
</tr>
