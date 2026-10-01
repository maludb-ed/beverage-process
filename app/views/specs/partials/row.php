<?php /** @var array $spec  @var bool $canEdit */
$id = (int) $spec['id']; $editUrl = '/specs/' . $id . '/edit'; $r = 'spec-row-' . $id;
$num = static fn($v) => $v === null ? '' : number_format((float) $v, (int) $spec['measurement_decimals']);
?>
<tr id="<?= e($r) ?>">
    <td id="<?= e($r) ?>-stage"></td>
    <td id="<?= e($r) ?>-measurement"><a <?= nav_attrs($editUrl) ?>><?= status_dot($spec['active'] ? 'success' : 'secondary') ?><span><?= e($spec['measurement_name']) ?></span></a> <small class="text-muted"><?= e($spec['measurement_unit']) ?></small></td>
    <td id="<?= e($r) ?>-min"><?= e($num($spec['min_value'])) ?></td>
    <td id="<?= e($r) ?>-max"><?= e($num($spec['max_value'])) ?></td>
    <td id="<?= e($r) ?>-target"><?= e($num($spec['target_value'])) ?></td>
    <td id="<?= e($r) ?>-active"><?= badge($spec['active'] ? 'Active' : 'Inactive', $spec['active'] ? 'success' : 'secondary') ?></td>
    <td id="<?= e($r) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canEdit): ?>
                <?= row_edit_button($r . '-edit-btn', $editUrl) ?>
                <a href="javascript:void(0);" id="<?= e($r) ?>-delete-btn" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Delete" hx-post="/specs/<?= e($id) ?>/delete" hx-target="#page-content" hx-swap="innerHTML" hx-confirm="Delete this spec? If readings use it, it is deactivated instead."><i class="feather-trash-2"></i></a>
            <?php endif; ?>
        </div>
    </td>
</tr>
