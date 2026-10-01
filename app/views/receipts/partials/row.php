<?php /** @var array $receipt  @var bool $canEdit */ $id = (int) $receipt['id']; $viewUrl = '/receipts/' . $id; ?>
<tr id="receipt-row-<?= e($id) ?>">
    <td id="receipt-row-<?= e($id) ?>-number"><a <?= nav_attrs($viewUrl) ?>><?= status_dot(status_color($receipt['status'])) ?><span><?= e($receipt['number']) ?></span></a></td>
    <td id="receipt-row-<?= e($id) ?>-supplier"><?= e($receipt['supplier_name']) ?></td>
    <td id="receipt-row-<?= e($id) ?>-purchase-order"><?php if ($receipt['purchase_order_id']): ?><a <?= nav_attrs('/purchase-orders/' . (int) $receipt['purchase_order_id']) ?>><?= e($receipt['po_number']) ?></a><?php else: ?><span class="text-muted">Unplanned</span><?php endif; ?></td>
    <td id="receipt-row-<?= e($id) ?>-received-at"><?= e(format_date($receipt['received_at'])) ?></td>
    <td id="receipt-row-<?= e($id) ?>-status"><?= status_badge($receipt['status']) ?></td>
    <td id="receipt-row-<?= e($id) ?>-lines"><?= e($receipt['line_count']) ?></td>
    <td id="receipt-row-<?= e($id) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canEdit && $receipt['status'] === 'draft'): ?><?= row_edit_button('receipt-row-' . $id . '-edit-btn', $viewUrl . '/edit') ?><?php endif; ?>
        </div>
    </td>
</tr>
