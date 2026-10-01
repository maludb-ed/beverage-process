<?php /** @var array $transfer  @var bool $canEdit */ $id = (int) $transfer['id']; $viewUrl = '/transfers/' . $id; ?>
<tr id="transfer-row-<?= e($id) ?>">
    <td id="transfer-row-<?= e($id) ?>-number"><a <?= nav_attrs($viewUrl) ?>><?= status_dot(status_color($transfer['status'])) ?><span><?= e($transfer['number']) ?></span></a></td>
    <td id="transfer-row-<?= e($id) ?>-from"><?= e($transfer['from_name']) ?></td>
    <td id="transfer-row-<?= e($id) ?>-to"><?= e($transfer['to_name']) ?></td>
    <td id="transfer-row-<?= e($id) ?>-lines"><?= e($transfer['line_count']) ?></td>
    <td id="transfer-row-<?= e($id) ?>-transferred"><?= e(format_date($transfer['transferred_at'])) ?></td>
    <td id="transfer-row-<?= e($id) ?>-status"><?= status_badge($transfer['status']) ?></td>
    <td id="transfer-row-<?= e($id) ?>-posted-by"><?= e($transfer['posted_by_name'] ?? '') ?></td>
    <td id="transfer-row-<?= e($id) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canEdit && $transfer['status'] === 'draft'): ?><?= row_edit_button('transfer-row-' . $id . '-edit-btn', $viewUrl . '/edit') ?><?php endif; ?>
        </div>
    </td>
</tr>
