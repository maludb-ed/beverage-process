<?php /** @var array $approval  @var bool $canEdit */ $id = (int) $approval['id']; $editUrl = '/approvals/' . $id . '/edit'; $r = 'approval-row-' . $id; ?>
<tr id="<?= e($r) ?>">
    <td id="<?= e($r) ?>-product"><a <?= nav_attrs($editUrl) ?>><?= status_dot(approval_status_color($approval['status'])) ?><span><?= e($approval['product_name']) ?></span></a></td>
    <td id="<?= e($r) ?>-kind"><?= e(humanize($approval['kind'])) ?></td>
    <td id="<?= e($r) ?>-package"><?= e($approval['package_name']) ?></td>
    <td id="<?= e($r) ?>-reference"><?= e($approval['reference_no']) ?></td>
    <td id="<?= e($r) ?>-status"><?= approval_status_badge($approval['status']) ?></td>
    <td id="<?= e($r) ?>-approved-on"><?= e(format_date($approval['approved_on'])) ?></td>
    <td id="<?= e($r) ?>-expires-on" class="<?= $approval['expiring_soon'] ? 'text-danger fw-semibold' : '' ?>"><?= e(format_date($approval['expires_on'])) ?></td>
    <td id="<?= e($r) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canEdit): ?><?= row_edit_button($r . '-edit-btn', $editUrl) ?><?php endif; ?>
        </div>
    </td>
</tr>
