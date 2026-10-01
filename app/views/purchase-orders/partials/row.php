<?php /** @var array $order  @var bool $canEdit */ $id = (int) $order['id']; $viewUrl = '/purchase-orders/' . $id; ?>
<tr id="purchase-order-row-<?= e($id) ?>">
    <td id="purchase-order-row-<?= e($id) ?>-number"><a <?= nav_attrs($viewUrl) ?>><?= status_dot(status_color($order['status'])) ?><span><?= e($order['number']) ?></span></a></td>
    <td id="purchase-order-row-<?= e($id) ?>-supplier"><?= e($order['supplier_name']) ?></td>
    <td id="purchase-order-row-<?= e($id) ?>-status"><?= status_badge($order['status']) ?></td>
    <td id="purchase-order-row-<?= e($id) ?>-ordered-on"><?= e(format_date($order['ordered_on'])) ?></td>
    <td id="purchase-order-row-<?= e($id) ?>-expected-on" class="<?= $order['overdue'] ? 'text-danger fw-semibold' : '' ?>"><?= e(format_date($order['expected_on'])) ?><?= $order['overdue'] ? ' <small>(overdue)</small>' : '' ?></td>
    <td id="purchase-order-row-<?= e($id) ?>-lines"><?= e($order['line_count']) ?></td>
    <td id="purchase-order-row-<?= e($id) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canEdit && $order['status'] === 'draft'): ?><?= row_edit_button('purchase-order-row-' . $id . '-edit-btn', $viewUrl . '/edit') ?><?php endif; ?>
        </div>
    </td>
</tr>
