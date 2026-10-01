<?php /** @var array $stock  @var bool $canEdit */
$id = (int) $stock['item_id'];
$unit = $stock['base_unit_code'];
$kind = inventory_unit_kind($stock['item_class']);
$shortfall = (float) $stock['shortfall'];
?>
<tr id="reorder-row-<?= e($id) ?>">
    <td id="reorder-row-<?= e($id) ?>-item"><a <?= nav_attrs('/items/' . $id) ?>><?= status_dot('warning') ?><span><?= e($stock['name']) ?></span></a> <small class="text-muted"><?= e($stock['code']) ?></small></td>
    <td id="reorder-row-<?= e($id) ?>-class"><?= e(humanize($stock['item_class'])) ?></td>
    <td id="reorder-row-<?= e($id) ?>-on-hand"><?= fmt_qty_html($stock['qty_on_hand'], $unit, 1, $kind) ?></td>
    <td id="reorder-row-<?= e($id) ?>-allocated"><?= fmt_qty_html($stock['qty_allocated'], $unit, 1, $kind) ?></td>
    <td id="reorder-row-<?= e($id) ?>-available"><?= fmt_qty_html($stock['qty_available'], $unit, 1, $kind) ?></td>
    <td id="reorder-row-<?= e($id) ?>-on-order"><?= fmt_qty_html($stock['qty_on_order'], $unit, 1, $kind) ?></td>
    <td id="reorder-row-<?= e($id) ?>-reorder-point"><?= fmt_qty_html($stock['reorder_point_base'], $unit, 1, $kind) ?></td>
    <td id="reorder-row-<?= e($id) ?>-shortfall" class="text-danger fw-semibold"><?= $shortfall > 0 ? fmt_qty_html($shortfall, $unit, 1, $kind) : '' ?></td>
    <td id="reorder-row-<?= e($id) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canEdit): ?><a id="item-row-<?= e($id) ?>-po-btn" class="btn btn-sm btn-light-brand" <?= nav_attrs('/purchase-orders/new?item=' . rawurlencode($stock['code'])) ?>><i class="feather-shopping-cart me-1"></i>Create PO</a><?php endif; ?>
        </div>
    </td>
</tr>
