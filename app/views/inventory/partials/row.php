<?php /** @var array $balance */
$key = (int) $balance['lot_id'] . '-' . (int) $balance['location_id'];
$kind = inventory_unit_kind($balance['item_class']);
$unit = $balance['base_unit_code'];
$expiring = $balance['expires_on'] !== null && $balance['expires_on'] < (new DateTimeImmutable(today()))->modify('+30 days')->format('Y-m-d');
?>
<tr id="inventory-row-<?= e($key) ?>">
    <td id="inventory-row-<?= e($key) ?>-item"><a <?= nav_attrs('/items/' . (int) $balance['item_id']) ?>><?= e($balance['item_name']) ?></a> <small class="text-muted"><?= e($balance['item_code']) ?></small></td>
    <td id="inventory-row-<?= e($key) ?>-lot"><a <?= nav_attrs('/lots/' . (int) $balance['lot_id']) ?>><?= status_dot(status_color($balance['quality_status'])) ?><span><?= e($balance['lot_number']) ?></span></a></td>
    <td id="inventory-row-<?= e($key) ?>-location"><?= e($balance['location_name']) ?> <small class="text-muted"><?= e(humanize($balance['tax_state'])) ?></small></td>
    <td id="inventory-row-<?= e($key) ?>-on-hand" class="fw-semibold"><?= fmt_qty_html($balance['qty_on_hand'], $unit, 1, $kind) ?></td>
    <td id="inventory-row-<?= e($key) ?>-allocated"><?= fmt_qty_html($balance['qty_allocated'], $unit, 1, $kind) ?></td>
    <td id="inventory-row-<?= e($key) ?>-available"><?= fmt_qty_html($balance['qty_available'], $unit, 1, $kind) ?></td>
    <td id="inventory-row-<?= e($key) ?>-expires" class="<?= $expiring ? 'text-danger' : '' ?>"><?= e(format_date($balance['expires_on'])) ?></td>
    <td id="inventory-row-<?= e($key) ?>-value" class="text-end"><?= e('$' . number_format((float) $balance['value_at_lot_cost'], 2)) ?></td>
</tr>
