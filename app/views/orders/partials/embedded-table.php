<?php
/** Orders tab on another entity's view (customer, product): the latest orders and a link to the full list.
 *  @var string $prefix  id prefix ("customer-view")  @var array $result  find_orders() result  @var bool $canPrice  @var ?string $newUrl  @var string $empty */
$newUrl = $newUrl ?? null;
?>
<div class="table-responsive">
    <table class="table table-hover mb-0" id="<?= e($prefix) ?>-orders-table">
        <thead class="thead-light"><tr><th>Order</th><th>Customer</th><th>Due</th><th>Units (open)</th><?php if ($canPrice): ?><th class="text-end">Value</th><?php endif; ?><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($result['rows'] as $o): $oid = (int) $o['id']; ?>
            <tr id="<?= e($prefix) ?>-order-row-<?= e($oid) ?>">
                <td><a <?= nav_attrs('/orders/' . $oid) ?>><?= status_dot(order_status_color($o['status'])) ?><?= e($o['number']) ?></a></td>
                <td><?= e($o['customer_name']) ?></td>
                <td class="<?= $o['overdue'] ? 'text-danger fw-semibold' : '' ?>"><?= e(format_date($o['requested_on'])) ?></td>
                <td><?= e(number_format((int) $o['units_ordered'])) ?><?= (int) $o['units_open'] > 0 ? ' <small class="text-muted">(' . e(number_format((int) $o['units_open'])) . ')</small>' : '' ?></td>
                <?php if ($canPrice): ?><td class="text-end"><?= $o['order_value'] !== null ? '$' . e(number_format((float) $o['order_value'], 2)) : '—' ?></td><?php endif; ?>
                <td><?= order_status_badge($o['status']) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($result['rows'] === []): ?><tr id="<?= e($prefix) ?>-orders-empty"><td colspan="<?= $canPrice ? 6 : 5 ?>" class="text-center text-muted py-4"><?= e($empty) ?></td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php if ($result['total'] > count($result['rows']) || $newUrl !== null): ?>
    <div class="p-4 border-top d-flex flex-wrap gap-2 justify-content-between align-items-center">
        <span class="fs-12 text-muted"><?= $result['total'] > count($result['rows']) ? e('Latest ' . count($result['rows']) . ' of ' . $result['total'] . '.') : '' ?></span>
        <?php if ($newUrl !== null): ?><?= nav_button($prefix . '-new-order-btn', $newUrl, 'New order') ?><?php endif; ?>
    </div>
<?php endif; ?>
