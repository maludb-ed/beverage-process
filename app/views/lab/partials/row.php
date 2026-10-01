<?php /** @var array $reading  @var array $user */
$id = (int) $reading['id']; $r = 'lab-row-' . $id;
$url = $reading['target_kind'] === 'batch' ? '/batches/' . (int) $reading['target_id'] : ($reading['target_kind'] === 'lot' ? '/lots/' . (int) $reading['target_id'] : null);
$targetLabel = $reading['target_number'] ?? humanize($reading['target_kind']) . ' #' . $reading['target_id'];
$color = ['pass' => 'success', 'fail' => 'danger', 'none' => 'secondary'][$reading['spec_result']] ?? 'secondary';
$decimals = (int) $reading['measurement_decimals'];
$fmt = static fn($v) => number_format((float) $v, $decimals, '.', ',');
$range = $reading['spec_id'] === null ? '' : ($reading['spec_min'] !== null && $reading['spec_max'] !== null ? $fmt($reading['spec_min']) . ' to ' . $fmt($reading['spec_max'])
    : ($reading['spec_min'] !== null ? 'min ' . $fmt($reading['spec_min']) : 'max ' . $fmt($reading['spec_max'])));
$canDelete = (int) $reading['analyst_id'] === (int) $user['id'] && $reading['is_today'] && user_can($user, 'quality');
?>
<tr id="<?= e($r) ?>">
    <td id="<?= e($r) ?>-taken"><?php if ($url): ?><a <?= nav_attrs($url) ?>><?= status_dot($color) ?><span><?= e(format_datetime($reading['taken_at'])) ?></span></a><?php else: ?><?= status_dot($color) ?><?= e(format_datetime($reading['taken_at'])) ?><?php endif; ?></td>
    <td id="<?= e($r) ?>-target"><?= $url ? '<a ' . nav_attrs($url) . '>' . e($targetLabel) . '</a>' : e($targetLabel) ?> <small class="text-muted"><?= e($reading['target_product'] ?? '') ?></small></td>
    <td id="<?= e($r) ?>-measurement"><?= e($reading['measurement_name']) ?></td>
    <td id="<?= e($r) ?>-value"><?= e($fmt($reading['value'])) ?> <small class="text-muted"><?= e($reading['measurement_unit']) ?></small></td>
    <td id="<?= e($r) ?>-stage"><?= e($reading['stage_name'] ?? '') ?></td>
    <td id="<?= e($r) ?>-spec"><?= badge(humanize($reading['spec_result']), $color) ?><?= $range !== '' ? ' <small class="text-muted">' . e($range) . '</small>' : '' ?></td>
    <td id="<?= e($r) ?>-lab"><?= $reading['is_lab'] ? '<i class="feather-check text-success"></i>' : '' ?></td>
    <td id="<?= e($r) ?>-analyst"><?= e($reading['analyst_name'] ?? '') ?></td>
    <td id="<?= e($r) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canDelete): ?>
                <a href="javascript:void(0);" id="<?= e($r) ?>-delete-btn" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Delete" hx-post="/lab/<?= e($id) ?>/delete" hx-target="#page-content" hx-swap="innerHTML" hx-confirm="Delete this reading?"><i class="feather-trash-2"></i></a>
            <?php endif; ?>
        </div>
    </td>
</tr>
