<?php /** @var array $vessels  @var array $query */
$refresh = '/tank-board/' . query_string(['premises_id' => $query['premises_id'] ?? null, 'kind' => $query['kind'] ?? null]);
?>
<div id="tank-board-results" hx-get="<?= e($refresh) ?>" hx-trigger="batchesChanged from:body" hx-target="#tank-board-results" hx-swap="outerHTML">
<div class="row g-3" id="tank-board-grid">
    <?php foreach ($vessels as $v): $vid = (int) $v['vessel_id']; $c = 'tank-board-vessel-' . $vid; $empty = $v['occupant_kind'] === null;
        $pct = $empty ? 0.0 : (float) $v['fill_pct'];
        $avatarColor = $empty ? 'bg-soft-secondary text-secondary' : 'bg-soft-info text-info';
        $avatarIcon = $v['vessel_kind'] === 'barrel' ? 'feather-circle' : 'feather-database';
        $url = $empty ? null : ($v['occupant_kind'] === 'batch' ? '/batches/' . (int) $v['occupant_id'] : '/lots/' . (int) $v['occupant_id']); ?>
    <div class="col-12 col-md-6 col-xl-4" id="<?= e($c) ?>">
        <div class="card mb-0">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div class="d-flex gap-3 align-items-center">
                        <div class="avatar-text avatar-lg <?= e($avatarColor) ?>"><i class="<?= e($avatarIcon) ?>"></i></div>
                        <div>
                            <div class="fw-bold text-dark" id="<?= e($c) ?>-name"><?= e($v['vessel_name']) ?></div>
                            <div class="fs-12 text-muted" id="<?= e($c) ?>-kind"><?= e(TANK_BOARD_KINDS[$v['vessel_kind']] ?? humanize($v['vessel_kind'])) ?> · <?= e(fmt_qty($v['capacity_l'], 'L', 0)) ?></div>
                        </div>
                    </div>
                    <?= status_badge($v['vessel_status'], $c . '-status') ?>
                </div>
                <?php if ($empty): ?>
                    <div class="text-muted fs-12 py-2" id="<?= e($c) ?>-occupant">Empty</div>
                    <div class="progress mt-2 ht-3"><div class="progress-bar bg-secondary" role="progressbar" style="width: 0%"></div></div>
                <?php else: ?>
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <a class="fw-semibold" id="<?= e($c) ?>-occupant" <?= nav_attrs($url) ?>><?= e($v['occupant_label']) ?></a>
                        <?php if ($v['stage_name']): ?><?= badge($v['stage_name'], 'info', $c . '-stage') ?><?php endif; ?>
                    </div>
                    <div class="fs-12 text-muted mt-1" id="<?= e($c) ?>-product"><?= e($v['product_name'] ?? ($v['occupant_kind'] === 'lot' ? 'Juice lot' : '')) ?></div>
                    <div class="d-flex align-items-center justify-content-between mt-3">
                        <span class="fs-12 text-dark" id="<?= e($c) ?>-volume"><?= fmt_qty_html($v['volume_l'], 'L', 1) ?></span>
                        <span class="fs-11 text-muted" id="<?= e($c) ?>-fill"><?= e(number_format($pct, 1)) ?>%</span>
                    </div>
                    <div class="progress mt-2 ht-3"><div class="progress-bar bg-<?= $pct > 100 ? 'danger' : 'info' ?>" role="progressbar" style="width: <?= e(min(100, $pct)) ?>%"></div></div>
                    <div class="fs-11 text-muted mt-2" id="<?= e($c) ?>-since">Since <?= e(format_date($v['occupied_since'])) ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if ($vessels === []): ?>
    <div class="col-12" id="tank-board-empty"><div class="card"><div class="card-body text-center py-5 text-muted"><i class="feather-inbox fs-1 d-block mb-3"></i>No vessels match.</div></div></div>
    <?php endif; ?>
</div>
</div>
