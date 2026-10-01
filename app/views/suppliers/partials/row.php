<?php /** @var array $supplier  @var bool $canEdit */ $id = (int) $supplier['id']; $viewUrl = '/suppliers/' . $id; $editUrl = $viewUrl . '/edit'; ?>
<tr id="supplier-row-<?= e($id) ?>">
    <td id="supplier-row-<?= e($id) ?>-name">
        <a <?= nav_attrs($viewUrl) ?>><?= status_dot($supplier['active'] ? 'success' : 'secondary') ?><span><?= e($supplier['name']) ?></span></a>
    </td>
    <td id="supplier-row-<?= e($id) ?>-kind"><?= badge(SUPPLIER_KINDS[$supplier['kind']] ?? $supplier['kind'], 'info') ?></td>
    <td id="supplier-row-<?= e($id) ?>-contact-name"><?= e($supplier['contact_name']) ?></td>
    <td id="supplier-row-<?= e($id) ?>-email"><?= e($supplier['email']) ?></td>
    <td id="supplier-row-<?= e($id) ?>-phone"><?= e($supplier['phone']) ?></td>
    <td id="supplier-row-<?= e($id) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canEdit): ?><?= row_edit_button('supplier-row-' . $id . '-edit-btn', $editUrl) ?><?php endif; ?>
        </div>
    </td>
</tr>
