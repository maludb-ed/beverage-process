<?php /** @var array $consumptions */ ?>
<div class="table-responsive">
    <table class="table table-hover mb-0" id="batch-view-consumptions-table">
        <thead class="thead-light"><tr><th>Item</th><th>Lot</th><th>Quantity</th><th>Purpose</th><th>Stage</th><th>When</th><th>By</th></tr></thead>
        <tbody>
        <?php foreach ($consumptions as $c): $cid = (int) $c['id']; $r = 'batch-consumption-row-' . $cid; ?>
            <tr id="<?= e($r) ?>">
                <td id="<?= e($r) ?>-item"><?= e($c['item_code']) ?> <small class="text-muted"><?= e($c['item_name']) ?></small></td>
                <td id="<?= e($r) ?>-lot"><a <?= nav_attrs('/lots/' . (int) $c['lot_id']) ?>><?= e($c['lot_number']) ?></a></td>
                <td id="<?= e($r) ?>-qty" class="fw-semibold"><?= fmt_qty_html($c['qty_base'], $c['base_unit_code'], 3, $c['item_class'] === 'fruit' ? 'fruit' : 'default') ?><?php if ($c['planned_qty_base'] !== null): ?> <small class="text-muted">planned <?= e(fmt_qty($c['planned_qty_base'], $c['base_unit_code'], 3)) ?></small><?php endif; ?></td>
                <td id="<?= e($r) ?>-purpose"><?= badge(BATCH_CONSUMPTION_PURPOSES[$c['purpose']] ?? humanize($c['purpose']), 'secondary') ?></td>
                <td id="<?= e($r) ?>-stage"><?= e($c['stage_name'] ?? '') ?></td>
                <td id="<?= e($r) ?>-when"><?= e(format_datetime($c['consumed_at'])) ?></td>
                <td id="<?= e($r) ?>-by"><?= e($c['actor_name'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($consumptions === []): ?><tr id="batch-view-consumptions-empty"><td colspan="7" class="text-center text-muted py-4">Nothing consumed yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
