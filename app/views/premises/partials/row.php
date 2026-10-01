<?php /** @var array $premises  @var bool $canEdit */ $id = (int) $premises['id']; $editUrl = '/premises/' . $id . '/edit'; ?>
<tr id="premises-row-<?= e($id) ?>">
    <td id="premises-row-<?= e($id) ?>-name">
        <a <?= $canEdit ? nav_attrs($editUrl) : 'href="javascript:void(0);"' ?>><?= status_dot($premises['active'] ? 'success' : 'secondary') ?><span><?= e($premises['name']) ?></span></a>
    </td>
    <td id="premises-row-<?= e($id) ?>-kind"><?= badge(PREMISES_KINDS[$premises['kind']] ?? $premises['kind'], 'info') ?></td>
    <td id="premises-row-<?= e($id) ?>-registry-number"><?= e($premises['registry_number']) ?></td>
    <td id="premises-row-<?= e($id) ?>-report-form"><?= e($premises['report_form']) ?></td>
    <td id="premises-row-<?= e($id) ?>-filing-frequency"><?= e(PREMISES_FREQUENCIES[$premises['filing_frequency']] ?? $premises['filing_frequency']) ?></td>
    <td id="premises-row-<?= e($id) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canEdit): ?><?= row_edit_button('premises-row-' . $id . '-edit-btn', $editUrl) ?><?php endif; ?>
        </div>
    </td>
</tr>
