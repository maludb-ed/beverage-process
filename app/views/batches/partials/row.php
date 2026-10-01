<?php /** @var array $batch  @var bool $canEdit */
$id = (int) $batch['id'];
$viewUrl = '/batches/' . $id;
$r = 'batch-row-' . $id;
?>
<tr id="<?= e($r) ?>">
    <td id="<?= e($r) ?>-number"><a <?= nav_attrs($viewUrl) ?>><?= status_dot(BATCH_STATUS_COLORS[$batch['status']] ?? 'secondary') ?><span><?= e($batch['number']) ?></span></a></td>
    <td id="<?= e($r) ?>-product"><?= e($batch['product_name']) ?></td>
    <td id="<?= e($r) ?>-stage"><?= badge($batch['stage_name'], 'info') ?></td>
    <td id="<?= e($r) ?>-vessels"><?= e($batch['vessels'] ?? '') ?></td>
    <td id="<?= e($r) ?>-volume"><?= fmt_qty_html($batch['current_volume_l'], 'L', 1) ?></td>
    <td id="<?= e($r) ?>-started"><?= e(format_date($batch['started_at'])) ?></td>
    <td id="<?= e($r) ?>-tax-class"><?= batches_tax_class_badge($batch['tax_class']) ?></td>
    <td id="<?= e($r) ?>-status"><?= batches_status_badge($batch['status']) ?></td>
    <td id="<?= e($r) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canEdit && $batch['status'] === 'active'): ?><?= row_edit_button($r . '-edit-btn', $viewUrl . '/edit') ?><?php endif; ?>
        </div>
    </td>
</tr>
