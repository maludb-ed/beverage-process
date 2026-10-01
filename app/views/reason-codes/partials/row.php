<?php /** @var array $reason  @var bool $canEdit */
$id = (int) $reason['id']; $editUrl = '/reason-codes/' . $id . '/edit';
$above = $reason['requires_approval_above'];
$aboveText = $above === null ? '' : (reason_code_threshold_is_volume($reason['applies_to']) ? fmt_qty($above, 'L') : rtrim(rtrim(number_format((float) $above, 4), '0'), '.') . ' (item base unit)');
?>
<tr id="reason-code-row-<?= e($id) ?>">
    <td id="reason-code-row-<?= e($id) ?>-code">
        <a <?= $canEdit ? nav_attrs($editUrl) : 'href="javascript:void(0);"' ?>><?= status_dot($reason['active'] ? 'success' : 'secondary') ?><span><?= e($reason['code']) ?></span></a>
    </td>
    <td id="reason-code-row-<?= e($id) ?>-name"><?= e($reason['name']) ?></td>
    <td id="reason-code-row-<?= e($id) ?>-applies-to"><?= badge(REASON_APPLIES_TO[$reason['applies_to']] ?? $reason['applies_to'], 'info') ?></td>
    <td id="reason-code-row-<?= e($id) ?>-ttb-category"><?= e(REASON_TTB_CATEGORIES[$reason['ttb_category']] ?? $reason['ttb_category']) ?></td>
    <td id="reason-code-row-<?= e($id) ?>-classification"><?= badge(REASON_CLASSIFICATIONS[$reason['classification']] ?? $reason['classification'], $reason['classification'] === 'expected' ? 'success' : 'warning') ?></td>
    <td id="reason-code-row-<?= e($id) ?>-requires-approval-above"><?= e($aboveText) ?></td>
    <td id="reason-code-row-<?= e($id) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canEdit): ?><?= row_edit_button('reason-code-row-' . $id . '-edit-btn', $editUrl) ?><?php endif; ?>
        </div>
    </td>
</tr>
