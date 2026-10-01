<?php /** @var array $keg  @var bool $canEdit */ $id = (int) $keg['keg_id']; $viewUrl = '/kegs/' . $id; ?>
<tr id="keg-row-<?= e($id) ?>">
    <td id="keg-row-<?= e($id) ?>-serial"><a <?= nav_attrs($viewUrl) ?>><?= status_dot(status_color($keg['state'])) ?><span><?= e($keg['serial']) ?></span></a></td>
    <td id="keg-row-<?= e($id) ?>-size"><?= fmt_qty_html($keg['size_l'], 'L', 2) ?></td>
    <td id="keg-row-<?= e($id) ?>-state"><?= status_badge($keg['state']) ?></td>
    <td id="keg-row-<?= e($id) ?>-contents"><?php if ($keg['current_lot_id']): ?><a <?= nav_attrs('/finished-lots/' . (int) $keg['current_lot_id']) ?>><?= e($keg['lot_number']) ?></a><?php else: ?><span class="text-muted">—</span><?php endif; ?></td>
    <td id="keg-row-<?= e($id) ?>-holder"><?= e($keg['holder_name'] ?? ($keg['current_holder_kind'] === 'unknown' ? 'Unknown' : '—')) ?></td>
    <td id="keg-row-<?= e($id) ?>-days"><?= e($keg['days_since_moved'] ?? '—') ?></td>
    <td id="keg-row-<?= e($id) ?>-fills"><?= e($keg['fill_count']) ?></td>
    <td id="keg-row-<?= e($id) ?>-actions" class="text-end">
        <div class="hstack gap-2 justify-content-end">
            <?php if ($canEdit): ?><?= row_edit_button('keg-row-' . $id . '-edit-btn', $viewUrl . '/edit') ?><?php endif; ?>
            <a id="keg-row-<?= e($id) ?>-view-btn" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="View" <?= nav_attrs($viewUrl) ?>><i class="feather-eye"></i></a>
        </div>
    </td>
</tr>
