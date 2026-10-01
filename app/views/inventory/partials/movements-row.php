<?php /** @var array $movement */
$id = (int) $movement['id'];
$kind = inventory_unit_kind($movement['item_class']);
$qty = (float) $movement['qty_base'];
$refUrl = isset(INVENTORY_REFERENCE_URLS[$movement['reference_kind']]) ? INVENTORY_REFERENCE_URLS[$movement['reference_kind']] . (int) $movement['reference_id'] : null;
$refLabel = humanize($movement['reference_kind']) . ' #' . (int) $movement['reference_id'];
?>
<tr id="movement-row-<?= e($id) ?>">
    <td id="movement-row-<?= e($id) ?>-occurred"><?= e(format_datetime($movement['occurred_at'])) ?></td>
    <td id="movement-row-<?= e($id) ?>-type"><?= badge(INVENTORY_TXN_TYPES[$movement['txn_type']] ?? humanize($movement['txn_type']), INVENTORY_TXN_COLORS[$movement['txn_type']] ?? 'secondary') ?></td>
    <td id="movement-row-<?= e($id) ?>-item"><?= e($movement['item_name']) ?></td>
    <td id="movement-row-<?= e($id) ?>-lot"><a <?= nav_attrs('/lots/' . (int) $movement['lot_id']) ?>><?= e($movement['lot_number']) ?></a></td>
    <td id="movement-row-<?= e($id) ?>-location"><?= e($movement['location_name']) ?></td>
    <td id="movement-row-<?= e($id) ?>-qty" class="fw-semibold <?= $qty < 0 ? 'text-danger' : '' ?>"><?= $qty > 0 ? '+' : '' ?><?= fmt_qty_html($qty, $movement['base_unit_code'], 1, $kind) ?></td>
    <td id="movement-row-<?= e($id) ?>-reason"><?= e($movement['reason_code'] ?? '') ?></td>
    <td id="movement-row-<?= e($id) ?>-reference"><?php if ($refUrl): ?><a <?= nav_attrs($refUrl) ?>><?= e($refLabel) ?></a><?php else: ?><?= e($refLabel) ?><?php endif; ?></td>
    <td id="movement-row-<?= e($id) ?>-actor"><?= e($movement['actor_name'] ?? '') ?></td>
</tr>
