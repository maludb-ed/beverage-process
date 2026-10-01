<?php /** @var array $record  @var array $user */
$id = (int) $record['id']; $r = 'sensory-row-' . $id;
$url = ($record['target_kind'] === 'batch' ? '/batches/' : '/lots/') . (int) $record['target_id'];
$color = ['pass' => 'success', 'hold' => 'warning', 'fail' => 'danger'][$record['verdict']] ?? 'secondary';
$faults = json_decode((string) $record['faults'], true) ?: [];
$faultText = implode(', ', array_map(static fn($f) => (SENSORY_FAULTS[$f['fault']] ?? $f['fault']) . ' ' . (int) $f['intensity'], $faults));
$canDelete = $record['panelist_id'] !== null && (int) $record['panelist_id'] === (int) $user['id'] && $record['is_today'] && user_can($user, 'quality');
?>
<tr id="<?= e($r) ?>">
    <td id="<?= e($r) ?>-panel-on"><a <?= nav_attrs($url) ?>><?= status_dot($color) ?><span><?= e(format_date($record['panel_on'])) ?></span></a></td>
    <td id="<?= e($r) ?>-target"><a <?= nav_attrs($url) ?>><?= e($record['target_number']) ?></a> <small class="text-muted"><?= e($record['target_product'] ?? '') ?></small></td>
    <td id="<?= e($r) ?>-panelist"><?= e($record['panelist_label'] ?? '') ?></td>
    <td id="<?= e($r) ?>-sample"><?= e($record['sample_code'] ?? '') ?></td>
    <td id="<?= e($r) ?>-verdict"><?= badge(humanize($record['verdict']), $color) ?></td>
    <td id="<?= e($r) ?>-faults"><?= e($faultText) ?></td>
    <td id="<?= e($r) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canDelete): ?>
                <a href="javascript:void(0);" id="<?= e($r) ?>-delete-btn" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Delete" hx-post="/sensory/<?= e($id) ?>/delete" hx-target="#page-content" hx-swap="innerHTML" hx-confirm="Delete this panel record?"><i class="feather-trash-2"></i></a>
            <?php endif; ?>
        </div>
    </td>
</tr>
