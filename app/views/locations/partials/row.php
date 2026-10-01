<?php /** @var array $location  @var bool $canEdit */ $id = (int) $location['id']; $editUrl = '/locations/' . $id . '/edit'; ?>
<tr id="location-row-<?= e($id) ?>">
    <td id="location-row-<?= e($id) ?>-name">
        <a <?= $canEdit ? nav_attrs($editUrl) : 'href="javascript:void(0);"' ?>><?= status_dot($location['active'] ? 'success' : 'secondary') ?><span><?= e($location['name']) ?></span></a>
    </td>
    <td id="location-row-<?= e($id) ?>-premises"><?= e($location['premises_name']) ?></td>
    <td id="location-row-<?= e($id) ?>-kind"><?= badge(LOCATION_KINDS[$location['kind']] ?? $location['kind'], 'info') ?></td>
    <td id="location-row-<?= e($id) ?>-tax-state"><?= badge(LOCATION_TAX_STATES[$location['tax_state']] ?? $location['tax_state'], $location['tax_state'] === 'bonded' ? 'info' : 'warning') ?></td>
    <td id="location-row-<?= e($id) ?>-allow-negative"><?= e(yes_no($location['allow_negative'])) ?></td>
    <td id="location-row-<?= e($id) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canEdit): ?><?= row_edit_button('location-row-' . $id . '-edit-btn', $editUrl) ?><?php endif; ?>
        </div>
    </td>
</tr>
