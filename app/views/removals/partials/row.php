<?php /** @var array $removal  @var bool $canEdit */ $id = (int) $removal['id']; $viewUrl = '/removals/' . $id; $out = $removal['direction'] === 'out'; ?>
<tr id="removal-row-<?= e($id) ?>">
    <td id="removal-row-<?= e($id) ?>-number"><a <?= nav_attrs($viewUrl) ?>><?= status_dot(status_color($removal['status'])) ?><span><?= e($removal['number']) ?></span></a></td>
    <td id="removal-row-<?= e($id) ?>-removed-at"><?= e(format_date($removal['removed_at'])) ?></td>
    <td id="removal-row-<?= e($id) ?>-direction"><i class="<?= $out ? 'feather-arrow-up-right text-danger' : 'feather-arrow-down-left text-success' ?>" data-bs-toggle="tooltip" title="<?= $out ? 'Out' : 'In (return)' ?>"></i></td>
    <td id="removal-row-<?= e($id) ?>-destination"><?= e(REMOVAL_DESTINATIONS[$removal['destination_kind']] ?? humanize($removal['destination_kind'])) ?><?php if ($removal['customer_name']): ?> <small class="text-muted d-block"><?= e($removal['customer_name']) ?></small><?php endif; ?></td>
    <td id="removal-row-<?= e($id) ?>-units"><?= e($removal['units']) ?></td>
    <td id="removal-row-<?= e($id) ?>-gallons"><?= $removal['wine_gallons'] !== null ? e(number_format((float) $removal['wine_gallons'], 2)) . ' gal' : '' ?></td>
    <td id="removal-row-<?= e($id) ?>-tax"><?= $removal['tax_determined'] && $removal['tax_amount'] !== null ? '$' . e(number_format((float) $removal['tax_amount'], 2)) : '' ?></td>
    <td id="removal-row-<?= e($id) ?>-status"><?= status_badge($removal['status']) ?></td>
    <td id="removal-row-<?= e($id) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canEdit && $removal['status'] === 'draft'): ?><?= row_edit_button('removal-row-' . $id . '-edit-btn', $viewUrl . '/edit') ?>
            <?php else: ?><a id="removal-row-<?= e($id) ?>-view-btn" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View" <?= nav_attrs($viewUrl) ?>><i class="feather-eye"></i></a><?php endif; ?>
        </div>
    </td>
</tr>
