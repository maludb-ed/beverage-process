<?php /** @var ?array $cost  @var array $yields */
$money = static fn($v) => $v === null ? '—' : '$' . number_format((float) $v, 2);
$perL = $cost['liquid_cost_per_l'] ?? null;
?>
<div class="card-body" id="batch-view-cost-summary">
    <?php if ($cost === null): ?>
        <p class="text-muted mb-0">No cost data yet.</p>
    <?php else: ?>
        <?= detail_row('batch-view-cost-material', 'Material', e($money($cost['material_cost']))) ?>
        <?= detail_row('batch-view-cost-packaging', 'Packaging', e($money($cost['packaging_cost']))) ?>
        <?= detail_row('batch-view-cost-overhead', 'Overhead', e($money($cost['overhead_cost']))) ?>
        <?= detail_row('batch-view-cost-total', 'Total', e($money($cost['total_cost']))) ?>
        <?= detail_row('batch-view-cost-per-liter', 'Per liter', e($perL === null ? '—' : '$' . number_format((float) $perL, 4))) ?>
        <?= detail_row('batch-view-cost-per-gallon', 'Per gallon', e($perL === null ? '—' : '$' . number_format((float) $perL * LITERS_PER_GALLON, 2))) ?>
        <?= detail_row('batch-view-cost-variance', 'Variance to standard', e($cost['variance_to_standard'] === null ? 'No standard' : $money($cost['variance_to_standard'])), true) ?>
    <?php endif; ?>
</div>
<div class="table-responsive border-top">
    <table class="table table-hover mb-0" id="batch-view-yields-table">
        <thead class="thead-light"><tr><th>Stage</th><th>In</th><th>Out</th><th>Actual loss</th><th>Expected loss</th></tr></thead>
        <tbody>
        <?php foreach ($yields as $i => $y): $r = 'batch-yield-row-' . $i; ?>
            <tr id="<?= e($r) ?>">
                <td id="<?= e($r) ?>-stage"><?= e($y['stage_name']) ?></td>
                <td id="<?= e($r) ?>-in"><?= fmt_qty_html($y['volume_in_l'], 'L', 1) ?></td>
                <td id="<?= e($r) ?>-out"><?= fmt_qty_html($y['volume_out_l'], 'L', 1) ?></td>
                <td id="<?= e($r) ?>-actual"><?= $y['actual_loss_pct'] !== null ? e(number_format((float) $y['actual_loss_pct'], 2)) . '%' : '' ?></td>
                <td id="<?= e($r) ?>-expected"><?= $y['expected_loss_pct'] !== null ? e(number_format((float) $y['expected_loss_pct'], 2)) . '%' : '' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($yields === []): ?><tr id="batch-view-yields-empty"><td colspan="5" class="text-center text-muted py-4">No stage events yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
