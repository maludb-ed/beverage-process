<?php /** @var array $vessel  @var bool $canEdit */ $id = (int) $vessel['id']; $editUrl = '/vessels/' . $id . '/edit'; ?>
<tr id="vessel-row-<?= e($id) ?>">
    <td id="vessel-row-<?= e($id) ?>-name">
        <a <?= $canEdit ? nav_attrs($editUrl) : 'href="javascript:void(0);"' ?>><?= status_dot(status_color($vessel['status'])) ?><span><?= e($vessel['name']) ?></span></a>
    </td>
    <td id="vessel-row-<?= e($id) ?>-kind"><?= e(VESSEL_KINDS[$vessel['kind']] ?? $vessel['kind']) ?></td>
    <td id="vessel-row-<?= e($id) ?>-capacity"><?= fmt_qty_html($vessel['capacity_l'], 'L', 1) ?></td>
    <td id="vessel-row-<?= e($id) ?>-status"><?= status_badge($vessel['status']) ?></td>
    <td id="vessel-row-<?= e($id) ?>-location"><?= e($vessel['location_name']) ?></td>
    <td id="vessel-row-<?= e($id) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canEdit): ?><?= row_edit_button('vessel-row-' . $id . '-edit-btn', $editUrl) ?><?php endif; ?>
        </div>
    </td>
</tr>
