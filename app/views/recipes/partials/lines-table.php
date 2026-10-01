<?php /** @var array $version  @var array $lines  @var float $scaleL  @var float $scaleDisplay */
$scaleLabel = rtrim(rtrim(number_format((float) to_display($scaleL, 'L'), 3), '0'), '.') . ' ' . display_unit('L');
?>
<div id="recipe-view-lines-table">
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="recipe-view-lines">
            <thead class="thead-light"><tr><th>#</th><th>Item</th><th>Stage</th><th>Purpose</th><th>Recipe quantity</th><th>At <?= e(fmt_qty($version['target_batch_volume_l'], 'L', 1)) ?></th><th id="recipe-view-scaled-heading">At <?= e($scaleLabel) ?></th><th>Mode</th></tr></thead>
            <tbody>
            <?php foreach ($lines as $line): $lid = (int) $line['id']; $kind = recipe_unit_kind($line['item_class']); ?>
                <tr id="recipe-line-view-row-<?= e($lid) ?>">
                    <td><?= e($line['seq']) ?></td>
                    <td id="recipe-line-view-row-<?= e($lid) ?>-item"><?= e($line['item_code']) ?> <small class="text-muted"><?= e($line['item_name']) ?></small></td>
                    <td id="recipe-line-view-row-<?= e($lid) ?>-stage"><?= e($line['stage_name']) ?></td>
                    <td id="recipe-line-view-row-<?= e($lid) ?>-purpose"><?= e(RECIPE_PURPOSES[$line['purpose']] ?? $line['purpose']) ?></td>
                    <td id="recipe-line-view-row-<?= e($lid) ?>-qty"><?= e(recipe_line_qty_text($line)) ?> <?= $line['scales'] ? '' : '<small class="text-muted">fixed</small>' ?></td>
                    <td id="recipe-line-view-row-<?= e($lid) ?>-at-target"><?= fmt_qty_html($line['qty_at_target'], $line['base_unit_code'], 3, $kind) ?></td>
                    <td id="recipe-line-view-row-<?= e($lid) ?>-scaled" class="fw-semibold"><?= fmt_qty_html($line['qty_scaled'], $line['base_unit_code'], 3, $kind) ?></td>
                    <td id="recipe-line-view-row-<?= e($lid) ?>-mode"><?= e(RECIPE_MODES[$line['consumption_mode']] ?? $line['consumption_mode']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($lines === []): ?><tr id="recipe-view-lines-empty"><td colspan="8" class="text-center text-muted py-4">No lines yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
