<?php /** @var array $lineage  @var array $trace */
$groups = ['parents' => 'Parents', 'children' => 'Children'];
?>
<div class="table-responsive">
    <table class="table table-hover mb-0" id="batch-view-lineage-table">
        <thead class="thead-light"><tr><th>Relation</th><th>Batch</th><th>Event</th><th>Volume</th><th>Fraction</th></tr></thead>
        <tbody>
        <?php foreach ($groups as $key => $label): foreach ($lineage[$key] as $link): $lid = (int) $link['id']; $r = 'batch-lineage-row-' . $lid; ?>
            <tr id="<?= e($r) ?>">
                <td id="<?= e($r) ?>-relation"><?= e($key === 'parents' ? 'Parent' : 'Child') ?></td>
                <td id="<?= e($r) ?>-batch"><a <?= nav_attrs('/batches/' . (int) $link['other_id']) ?>><?= status_dot(BATCH_STATUS_COLORS[$link['other_status']] ?? 'secondary') ?><?= e($link['other_number']) ?></a></td>
                <td id="<?= e($r) ?>-event"><?= badge(humanize($link['event_kind']), 'info') ?></td>
                <td id="<?= e($r) ?>-volume" class="fw-semibold"><?= fmt_qty_html($link['volume_l'], 'L', 1) ?></td>
                <td id="<?= e($r) ?>-fraction"><?= e(number_format(100 * (float) $link['fraction'], 1)) ?>%</td>
            </tr>
        <?php endforeach; endforeach; ?>
        <?php if ($lineage['parents'] === [] && $lineage['children'] === []): ?><tr id="batch-view-lineage-empty"><td colspan="5" class="text-center text-muted py-4">No splits or blends.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<div class="px-4 pt-4 pb-2 fw-semibold">Juice and fruit traced back</div>
<div class="table-responsive">
    <table class="table table-hover mb-0" id="batch-view-trace-table">
        <thead class="thead-light"><tr><th>Level</th><th>Lot</th><th>Item</th><th>Detail</th></tr></thead>
        <tbody>
        <?php foreach ($trace as $i => $node): $r = 'batch-trace-row-' . $i; $d = $node['detail']; ?>
            <tr id="<?= e($r) ?>">
                <td id="<?= e($r) ?>-level"><?= badge($node['kind'] === 'fruit_lot' ? 'Fruit' : 'Ingredient', $node['kind'] === 'fruit_lot' ? 'success' : 'info') ?> <small class="text-muted"><?= e((int) $node['level']) ?></small></td>
                <td id="<?= e($r) ?>-lot"><a <?= nav_attrs('/lots/' . (int) $node['id']) ?>><?= e($node['label']) ?></a></td>
                <td id="<?= e($r) ?>-item"><?= e($d['item'] ?? '') ?></td>
                <td id="<?= e($r) ?>-detail"><?php if ($node['kind'] === 'fruit_lot'): ?><?= e(($d['press_run'] ?? '') . ' · ' . fmt_qty($d['kg'] ?? 0, 'kg', 1, 'fruit')) ?><?php else: ?><?= e(humanize($d['purpose'] ?? '')) ?><?php endif; ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($trace === []): ?><tr id="batch-view-trace-empty"><td colspan="4" class="text-center text-muted py-4">Nothing to trace.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
