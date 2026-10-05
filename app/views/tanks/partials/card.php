<?php /** @var array $v  @var int $grid  @var bool $canArrange — one tank on the floor */
$vid = (int) $v['vessel_id']; $c = 'tank-view-vessel-' . $vid;
$empty = $v['occupant_kind'] === null;
$pct = (float) $v['fill_pct'];
$content = $v['content_kind'] !== null ? TANK_VIEW_CONTENT[$v['content_kind']] : null;
$url = $empty ? '/vessels/' . $vid . '/edit' : ($v['occupant_kind'] === 'batch' ? '/batches/' . (int) $v['occupant_id'] : '/lots/' . (int) $v['occupant_id']);
$what = $empty ? 'Empty' : ($v['occupant_kind'] === 'batch' ? (string) $v['product_name'] : (string) ($v['lot_item_name'] ?? 'Juice'));
$sub = $empty ? ucfirst(str_replace('_', ' ', (string) $v['vessel_status'])) : (string) $v['occupant_label'];
$title = e($v['vessel_name']) . ' · ' . e(TANK_BOARD_KINDS[$v['vessel_kind']] ?? humanize($v['vessel_kind'])) . ' · ' . e(fmt_qty($v['capacity_l'], 'L', 0))
    . ' · ' . e(humanize($v['vessel_status'])) . ($empty ? '' : ' · since ' . e(format_date($v['occupied_since'])));
$statusClass = $v['vessel_status'] === 'out_of_service' ? ' tank-view-card-out' : ($v['vessel_status'] === 'cleaning' ? ' tank-view-card-cleaning' : '');
?>
<div class="tank-view-card<?= $statusClass ?><?= $v['placed'] ? '' : ' tank-view-card-unplaced' ?>" id="<?= e($c) ?>" data-vessel-id="<?= $vid ?>"
     style="left: <?= (int) $v['board_x'] * $grid ?>px; top: <?= (int) $v['board_y'] * $grid ?>px;" title="<?= $title ?>">
    <div class="tank-view-handle<?= $canArrange ? '' : ' tank-view-handle-fixed' ?>" id="<?= e($c) ?>-name"><?= e($v['vessel_name']) ?></div>
    <div class="tank-view-glass" id="<?= e($c) ?>-glass">
        <div class="tank-view-liquid <?= e($content['class'] ?? '') ?>" style="height: <?= e(min(100, max(0, $pct))) ?>%"></div>
        <div class="tank-view-pct" id="<?= e($c) ?>-fill"><?= e(number_format($pct, 0)) ?>%</div>
    </div>
    <div class="tank-view-what" id="<?= e($c) ?>-what">
        <?php if ($content !== null): ?><span class="tank-view-dot <?= e($content['class']) ?>"></span><?php endif; ?><?= e($what) ?>
    </div>
    <div class="tank-view-sub" id="<?= e($c) ?>-occupant"><?= e($sub) ?><?php if (!$empty && $v['stage_name']): ?> · <?= e($v['stage_name']) ?><?php endif; ?></div>
    <div class="tank-view-volume" id="<?= e($c) ?>-volume"><?= $empty ? e(fmt_qty($v['capacity_l'], 'L', 0)) . ' capacity' : e(fmt_qty($v['volume_l'], 'L', 0)) . ' of ' . e(fmt_qty($v['capacity_l'], 'L', 0)) ?></div>
    <div class="tank-view-actions hstack gap-1">
        <a class="btn btn-sm btn-light flex-fill" id="<?= e($c) ?>-open" <?= nav_attrs($url) ?>><?= $empty ? 'Vessel' : 'Open' ?></a>
        <?php if (!$empty && $v['occupant_kind'] === 'batch'): ?>
            <a class="btn btn-sm btn-light flex-fill" id="<?= e($c) ?>-move" <?= nav_attrs('/batches/' . (int) $v['occupant_id'] . '/transfer') ?>>Move <i class="feather-arrow-right"></i></a>
        <?php elseif ($canArrange): ?>
            <a class="btn btn-sm btn-light" id="<?= e($c) ?>-edit" <?= nav_attrs('/vessels/' . $vid . '/edit') ?> title="Edit vessel"><i class="feather-edit-2"></i></a>
        <?php endif; ?>
    </div>
</div>
