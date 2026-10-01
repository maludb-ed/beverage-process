<?php /** @var array $diff */
$current = $diff['current'];
$other = $diff['other'];
$lineName = static fn(array $l) => e($l['item_code']) . ' <small class="text-muted">' . e($l['stage_name'] ?? $l['stage_code']) . ' / ' . e(RECIPE_PURPOSES[$l['purpose']] ?? $l['purpose']) . '</small>';
$none = '<tr><td class="text-muted">None</td></tr>';
?>
<div id="recipe-view-diff-result">
    <p class="text-muted fs-12" id="recipe-view-diff-caption">v<?= e($current['version_no']) ?> compared with v<?= e($other['version_no']) ?>: added means in v<?= e($current['version_no']) ?> only, removed means in v<?= e($other['version_no']) ?> only.</p>
    <h6 class="fw-bold">Lines</h6>
    <div class="table-responsive mb-4">
        <table class="table table-sm mb-0" id="recipe-view-diff-lines">
            <thead class="thead-light"><tr><th colspan="2">Added</th></tr></thead>
            <tbody id="recipe-view-diff-lines-added">
            <?php foreach ($diff['lines']['added'] as $l): ?><tr><td><?= $lineName($l) ?></td><td><?= e(recipe_line_qty_text($l)) ?></td></tr><?php endforeach; ?>
            <?php if ($diff['lines']['added'] === []) { echo $none; } ?>
            </tbody>
            <thead class="thead-light"><tr><th colspan="2">Removed</th></tr></thead>
            <tbody id="recipe-view-diff-lines-removed">
            <?php foreach ($diff['lines']['removed'] as $l): ?><tr><td><?= $lineName($l) ?></td><td><?= e(recipe_line_qty_text($l)) ?></td></tr><?php endforeach; ?>
            <?php if ($diff['lines']['removed'] === []) { echo $none; } ?>
            </tbody>
            <thead class="thead-light"><tr><th colspan="2">Changed</th></tr></thead>
            <tbody id="recipe-view-diff-lines-changed">
            <?php foreach ($diff['lines']['changed'] as $c): ?>
                <tr><td><?= $lineName($c['row']) ?></td><td>
                    <?php if (isset($c['changes']['qty_per_batch_base']) || isset($c['changes']['qty_per_l'])): ?><?= e(recipe_line_qty_text($c['other'])) ?> &rarr; <strong><?= e(recipe_line_qty_text($c['row'])) ?></strong><br /><?php endif; ?>
                    <?php if (isset($c['changes']['consumption_mode'])): ?>Mode: <?= e($c['changes']['consumption_mode']['from']) ?> &rarr; <strong><?= e($c['changes']['consumption_mode']['to']) ?></strong><?php endif; ?>
                </td></tr>
            <?php endforeach; ?>
            <?php if ($diff['lines']['changed'] === []) { echo $none; } ?>
            </tbody>
        </table>
    </div>
    <h6 class="fw-bold">Stages</h6>
    <div class="table-responsive">
        <table class="table table-sm mb-0" id="recipe-view-diff-stages">
            <thead class="thead-light"><tr><th colspan="2">Added</th></tr></thead>
            <tbody id="recipe-view-diff-stages-added">
            <?php foreach ($diff['stages']['added'] as $s): ?><tr><td><?= e($s['stage_name']) ?></td><td><?= e(number_format((float) $s['expected_loss_pct'], 2)) ?>% loss</td></tr><?php endforeach; ?>
            <?php if ($diff['stages']['added'] === []) { echo $none; } ?>
            </tbody>
            <thead class="thead-light"><tr><th colspan="2">Removed</th></tr></thead>
            <tbody id="recipe-view-diff-stages-removed">
            <?php foreach ($diff['stages']['removed'] as $s): ?><tr><td><?= e($s['stage_name']) ?></td><td><?= e(number_format((float) $s['expected_loss_pct'], 2)) ?>% loss</td></tr><?php endforeach; ?>
            <?php if ($diff['stages']['removed'] === []) { echo $none; } ?>
            </tbody>
            <thead class="thead-light"><tr><th colspan="2">Changed</th></tr></thead>
            <tbody id="recipe-view-diff-stages-changed">
            <?php foreach ($diff['stages']['changed'] as $c): ?>
                <tr><td><?= e($c['row']['stage_name']) ?></td><td>
                    <?php foreach ($c['changes'] as $field => $change): ?><?= e(humanize(str_replace('expected_', '', $field))) ?>: <?= e($change['from'] ?? '—') ?> &rarr; <strong><?= e($change['to'] ?? '—') ?></strong><br /><?php endforeach; ?>
                </td></tr>
            <?php endforeach; ?>
            <?php if ($diff['stages']['changed'] === []) { echo $none; } ?>
            </tbody>
        </table>
    </div>
</div>
