<?php /** @var array $order  @var bool $canEdit */ $id = (int) $order['id']; $viewUrl = '/production-orders/' . $id; ?>
<tr id="production-order-row-<?= e($id) ?>">
    <td id="production-order-row-<?= e($id) ?>-number"><a <?= nav_attrs($viewUrl) ?>><?= production_order_status_dot($order['status']) ?><span><?= e($order['number']) ?></span></a></td>
    <td id="production-order-row-<?= e($id) ?>-product"><?= e($order['product_name']) ?></td>
    <td id="production-order-row-<?= e($id) ?>-recipe">v<?= e($order['version_no']) ?></td>
    <td id="production-order-row-<?= e($id) ?>-volume"><?= fmt_qty_html($order['planned_volume_l'], 'L') ?></td>
    <td id="production-order-row-<?= e($id) ?>-pitch-on"><?= e(format_date($order['planned_pitch_on'])) ?></td>
    <td id="production-order-row-<?= e($id) ?>-package-on"><?= e(format_date($order['planned_package_on'])) ?></td>
    <td id="production-order-row-<?= e($id) ?>-status"><?= production_order_status_badge($order['status']) ?></td>
    <td id="production-order-row-<?= e($id) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canEdit && $order['status'] === 'planned'): ?><?= row_edit_button('production-order-row-' . $id . '-edit-btn', $viewUrl . '/edit') ?><?php endif; ?>
            <a id="production-order-row-<?= e($id) ?>-view-btn" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View" <?= nav_attrs($viewUrl) ?>><i class="feather-eye"></i></a>
        </div>
    </td>
</tr>
