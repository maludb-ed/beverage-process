<?php /** @var array $lot */ $id = (int) $lot['id']; $kind = $lot['item_class'] === 'fruit' ? 'fruit' : 'default'; ?>
<tr id="lot-row-<?= e($id) ?>">
    <td id="lot-row-<?= e($id) ?>-lot-number"><a <?= nav_attrs('/lots/' . $id) ?>><?= status_dot(status_color($lot['quality_status'])) ?><span><?= e($lot['lot_number']) ?></span></a><?= $lot['supplier_lot_number'] ? ' <small class="text-muted">(' . e($lot['supplier_lot_number']) . ')</small>' : '' ?></td>
    <td id="lot-row-<?= e($id) ?>-item"><?= e($lot['item_code']) ?> <small class="text-muted"><?= e($lot['item_name']) ?></small></td>
    <td id="lot-row-<?= e($id) ?>-quality-status"><?= status_badge($lot['quality_status']) ?></td>
    <td id="lot-row-<?= e($id) ?>-received-on"><?= e(format_date($lot['received_on'] ?? $lot['produced_on'])) ?></td>
    <td id="lot-row-<?= e($id) ?>-expires-on" class="<?= $lot['expiring_soon'] ? 'text-danger fw-semibold' : '' ?>"><?= e(format_date($lot['expires_on'])) ?></td>
    <td id="lot-row-<?= e($id) ?>-on-hand"><?= fmt_qty_html($lot['qty_on_hand'], $lot['base_unit_code'], 1, $kind) ?></td>
    <td id="lot-row-<?= e($id) ?>-supplier"><?= e($lot['supplier_name']) ?></td>
    <td id="lot-row-<?= e($id) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end"><?= row_edit_button('lot-row-' . $id . '-edit-btn', '/lots/' . $id . '/edit') ?></div>
    </td>
</tr>
