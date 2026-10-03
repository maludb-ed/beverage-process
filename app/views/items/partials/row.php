<?php /** @var array $item  @var array $classes  @var bool $canEdit */ $id = (int) $item['id']; $viewUrl = '/items/' . $id; $editUrl = $viewUrl . '/edit'; ?>
<tr id="item-row-<?= e($id) ?>">
    <td id="item-row-<?= e($id) ?>-code">
        <a <?= nav_attrs($viewUrl) ?>><?= status_dot($item['active'] ? 'success' : 'secondary') ?><span><?= e($item['code']) ?></span></a>
    </td>
    <td id="item-row-<?= e($id) ?>-name"><?= e($item['name']) ?></td>
    <td id="item-row-<?= e($id) ?>-item-class"><?= badge($classes[$item['item_class']] ?? $item['item_class'], 'info') ?></td>
    <td id="item-row-<?= e($id) ?>-base-unit-code"><?= e($item['base_unit_code']) ?></td>
    <td id="item-row-<?= e($id) ?>-lot-controlled"><?= e(yes_no($item['lot_controlled'])) ?></td>
    <td id="item-row-<?= e($id) ?>-default-receipt-status"><?= status_badge($item['default_receipt_status']) ?></td>
    <td id="item-row-<?= e($id) ?>-reorder-point"><?= fmt_qty_html($item['reorder_point_base'], $item['base_unit_code'], 1, items_unit_kind($item['item_class'])) ?></td>
    <td id="item-row-<?= e($id) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canEdit): ?><?= row_edit_button('item-row-' . $id . '-edit-btn', $editUrl) ?><?php endif; ?>
        </div>
    </td>
</tr>
