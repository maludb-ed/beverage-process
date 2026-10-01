<?php /** @var array $transfers */ ?>
<div class="table-responsive">
    <table class="table table-hover mb-0" id="batch-view-transfers-table">
        <thead class="thead-light"><tr><th>From</th><th>To</th><th>Volume</th><th>Loss</th><th>When</th><th>By</th></tr></thead>
        <tbody>
        <?php foreach ($transfers as $t): $tid = (int) $t['id']; $r = 'batch-transfer-row-' . $tid; ?>
            <tr id="<?= e($r) ?>">
                <td id="<?= e($r) ?>-from"><?= e($t['from_vessel_name']) ?></td>
                <td id="<?= e($r) ?>-to"><?= e($t['to_vessel_name']) ?></td>
                <td id="<?= e($r) ?>-volume" class="fw-semibold"><?= fmt_qty_html($t['volume_l'], 'L', 1) ?></td>
                <td id="<?= e($r) ?>-loss"><?= fmt_qty_html($t['loss_l'], 'L', 1) ?></td>
                <td id="<?= e($r) ?>-when"><?= e(format_datetime($t['transferred_at'])) ?></td>
                <td id="<?= e($r) ?>-by"><?= e($t['actor_name'] ?? '') ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if ($transfers === []): ?><tr id="batch-view-transfers-empty"><td colspan="6" class="text-center text-muted py-4">No transfers yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
