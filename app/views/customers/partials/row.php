<?php /** @var array $customer  @var bool $canEdit */ $id = (int) $customer['id']; $viewUrl = '/customers/' . $id; ?>
<tr id="customer-row-<?= e($id) ?>">
    <td id="customer-row-<?= e($id) ?>-name"><a <?= nav_attrs($viewUrl) ?>><?= status_dot($customer['active'] ? 'success' : 'secondary') ?><span><?= e($customer['name']) ?></span></a></td>
    <td id="customer-row-<?= e($id) ?>-kind"><?= badge(CUSTOMER_KINDS[$customer['kind']] ?? humanize($customer['kind']), 'info') ?></td>
    <td id="customer-row-<?= e($id) ?>-default-destination"><?= e(CUSTOMER_DESTINATIONS[$customer['default_destination']] ?? humanize($customer['default_destination'])) ?></td>
    <td id="customer-row-<?= e($id) ?>-kegs-out"><?= e($customer['kegs_out']) ?></td>
    <td id="customer-row-<?= e($id) ?>-last-removal"><?= e(format_date($customer['last_removal_at']) ?: '—') ?></td>
    <td id="customer-row-<?= e($id) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canEdit): ?><?= row_edit_button('customer-row-' . $id . '-edit-btn', $viewUrl . '/edit') ?><?php endif; ?>
            <a id="customer-row-<?= e($id) ?>-view-btn" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View" <?= nav_attrs($viewUrl) ?>><i class="feather-eye"></i></a>
        </div>
    </td>
</tr>
