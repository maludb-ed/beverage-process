<?php /** @var array $order  @var bool $canEdit  @var bool $canPrice */ $id = (int) $order['id']; $viewUrl = '/orders/' . $id; ?>
<tr id="order-row-<?= e($id) ?>">
    <td id="order-row-<?= e($id) ?>-number"><a <?= nav_attrs($viewUrl) ?>><?= status_dot(order_status_color($order['status'])) ?><span><?= e($order['number']) ?></span></a></td>
    <td id="order-row-<?= e($id) ?>-customer"><?= e($order['customer_name']) ?></td>
    <td id="order-row-<?= e($id) ?>-reference"><?= e($order['customer_reference']) ?></td>
    <td id="order-row-<?= e($id) ?>-status"><?= order_status_badge($order['status']) ?></td>
    <td id="order-row-<?= e($id) ?>-ordered-on"><?= e(format_date($order['ordered_on'])) ?></td>
    <td id="order-row-<?= e($id) ?>-requested-on" class="<?= $order['overdue'] ? 'text-danger fw-semibold' : '' ?>"><?= e(format_date($order['requested_on'])) ?><?= $order['overdue'] ? ' <small>(overdue)</small>' : '' ?></td>
    <td id="order-row-<?= e($id) ?>-units"><?= e(number_format((int) $order['units_ordered'])) ?><?= (int) $order['units_open'] > 0 ? ' <small class="text-muted">(' . e(number_format((int) $order['units_open'])) . ' open)</small>' : '' ?></td>
    <?php if ($canPrice): ?><td id="order-row-<?= e($id) ?>-value" class="text-end"><?= $order['order_value'] !== null ? '$' . e(number_format((float) $order['order_value'], 2)) : '<span class="text-muted">—</span>' ?></td><?php endif; ?>
    <td id="order-row-<?= e($id) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canEdit && in_array($order['status'], ['draft', 'confirmed'], true)): ?><?= row_edit_button('order-row-' . $id . '-edit-btn', $viewUrl . '/edit') ?><?php endif; ?>
        </div>
    </td>
</tr>
