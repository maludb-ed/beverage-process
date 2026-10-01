<?php /** @var array $run  @var bool $canEdit */ $id = (int) $run['id']; $viewUrl = '/packaging-runs/' . $id; ?>
<tr id="packaging-run-row-<?= e($id) ?>">
    <td id="packaging-run-row-<?= e($id) ?>-number"><a <?= nav_attrs($viewUrl) ?>><?= status_dot(status_color($run['status'])) ?><span><?= e($run['number']) ?></span></a></td>
    <td id="packaging-run-row-<?= e($id) ?>-batch"><a <?= nav_attrs('/batches/' . (int) $run['batch_id']) ?>><?= e($run['batch_number']) ?></a> <small class="text-muted"><?= e($run['product_name']) ?></small></td>
    <td id="packaging-run-row-<?= e($id) ?>-package"><?= e($run['configuration_name']) ?></td>
    <td id="packaging-run-row-<?= e($id) ?>-run-on"><?= e(format_date($run['run_on'])) ?></td>
    <td id="packaging-run-row-<?= e($id) ?>-volume-in"><?= fmt_qty_html($run['volume_in_l'], 'L', 1) ?></td>
    <td id="packaging-run-row-<?= e($id) ?>-units-out"><?= e($run['units_out'] ?? '—') ?></td>
    <td id="packaging-run-row-<?= e($id) ?>-loss"><?php if ($run['loss_l'] !== null): ?><?= fmt_qty_html($run['loss_l'], 'L', 1) ?> <small class="text-muted"><?= e(number_format((float) $run['loss_pct'], 1)) ?>%</small><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
    <td id="packaging-run-row-<?= e($id) ?>-status"><?= status_badge($run['status']) ?></td>
    <td id="packaging-run-row-<?= e($id) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canEdit && $run['status'] === 'draft'): ?><?= row_edit_button('packaging-run-row-' . $id . '-edit-btn', $viewUrl . '/edit') ?>
            <?php else: ?><a id="packaging-run-row-<?= e($id) ?>-view-btn" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View" <?= nav_attrs($viewUrl) ?>><i class="feather-eye"></i></a><?php endif; ?>
        </div>
    </td>
</tr>
