<?php /** @var array $lot */ $id = (int) $lot['lot_id']; $rid = $id . '-' . (int) $lot['location_id']; $viewUrl = '/finished-lots/' . $id; ?>
<tr id="finished-lot-row-<?= e($rid) ?>">
    <td id="finished-lot-row-<?= e($rid) ?>-lot"><a <?= nav_attrs($viewUrl) ?>><?= status_dot($lot['tax_state'] === 'bonded' ? 'info' : 'success') ?><span><?= e($lot['lot_number']) ?></span></a></td>
    <td id="finished-lot-row-<?= e($rid) ?>-product"><?= e($lot['product_name']) ?> <small class="text-muted"><?= e($lot['package_name']) ?></small></td>
    <td id="finished-lot-row-<?= e($rid) ?>-batch"><?= e($lot['batch_number']) ?></td>
    <td id="finished-lot-row-<?= e($rid) ?>-packaged"><?= e(format_date($lot['packaged_on'])) ?></td>
    <td id="finished-lot-row-<?= e($rid) ?>-on-hand"><?= e(number_format((float) $lot['units_on_hand'])) ?> <small class="text-muted"><?= e(number_format((float) $lot['units_available'])) ?> available</small></td>
    <td id="finished-lot-row-<?= e($rid) ?>-volume"><?= fmt_qty_html($lot['volume_on_hand_l'], 'L', 1) ?></td>
    <td id="finished-lot-row-<?= e($rid) ?>-tax-class"><?= badge(humanize($lot['tax_class']), $lot['tax_class'] === 'hard_cider' ? 'success' : 'warning') ?></td>
    <td id="finished-lot-row-<?= e($rid) ?>-location"><?= e($lot['location_name']) ?></td>
    <td id="finished-lot-row-<?= e($rid) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end"><a id="finished-lot-row-<?= e($rid) ?>-view-btn" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View" <?= nav_attrs($viewUrl) ?>><i class="feather-eye"></i></a></div>
    </td>
</tr>
