<?php /** @var array $board  @var array $query  @var bool $canEdit */
$refresh = '/racks/' . query_string(['q' => $query['q'] ?: null, 'premises_id' => $query['premises_id'] ?? null, 'area_id' => $query['area_id'] ?? null]);
$lastArea = null;
?>
<div id="rack-board-results" hx-get="<?= e($refresh) ?>" hx-trigger="locationChanged from:body, inventoryChanged from:body" hx-target="#rack-board-results" hx-swap="outerHTML">
<div class="row g-3" id="rack-board-grid">
    <?php foreach ($board as $cell): $rack = $cell['rack']; $lots = $cell['lots'];
        $loose = $rack['id'] === null;
        $c = $loose ? 'rack-board-loose-' . (int) $rack['area_location_id'] : 'rack-board-rack-' . (int) $rack['id'];
        $areaChanged = $lastArea !== (int) $rack['area_location_id']; $lastArea = (int) $rack['area_location_id'];
        $avatarColor = $loose ? 'bg-soft-warning text-warning' : ($lots === [] ? 'bg-soft-secondary text-secondary' : 'bg-soft-primary text-primary'); ?>
    <?php if ($areaChanged): ?>
    <div class="col-12" id="rack-board-area-<?= e($lastArea) ?>"><h6 class="fw-bold mb-0 mt-2 text-uppercase fs-12 text-muted"><?= e($rack['area_name']) ?></h6></div>
    <?php endif; ?>
    <div class="col-12 col-md-6 col-xl-4" id="<?= e($c) ?>">
        <div class="card mb-0">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between mb-3">
                    <div class="d-flex gap-3 align-items-center">
                        <div class="avatar-text avatar-lg <?= e($avatarColor) ?>"><?= $loose ? '<i class="feather-alert-triangle"></i>' : '<span class="fw-bold">' . e($rack['rack_number']) . '</span>' ?></div>
                        <div>
                            <div class="fw-bold text-dark" id="<?= e($c) ?>-name"><?= e($rack['name']) ?></div>
                            <div class="fs-12 text-muted" id="<?= e($c) ?>-area"><?= e($rack['area_name']) ?><?= $lots !== [] ? ' · ' . e(count($lots)) . ' ' . (count($lots) === 1 ? 'lot' : 'lots') : '' ?></div>
                        </div>
                    </div>
                    <div class="hstack gap-2">
                        <?php if (!$loose && !$rack['active']): ?><?= badge('Inactive', 'secondary', $c . '-inactive') ?><?php endif; ?>
                        <?php if (!$loose && $canEdit): ?><?= row_edit_button($c . '-edit-btn', '/racks/' . (int) $rack['id'] . '/edit') ?><?php endif; ?>
                    </div>
                </div>
                <?php if ($lots === []): ?>
                    <div class="text-muted fs-12 py-2" id="<?= e($c) ?>-empty">Empty</div>
                <?php else: ?>
                    <ul class="list-unstyled mb-0" id="<?= e($c) ?>-lots">
                    <?php foreach ($lots as $i => $lot): $lid = $c . '-lot-' . (int) $lot['lot_id'];
                        $isFinished = $lot['batch_id'] !== null;
                        $lotUrl = $isFinished ? '/finished-lots/' . (int) $lot['lot_id'] : '/lots/' . (int) $lot['lot_id'];
                        $qty = $lot['item_class'] === 'finished_good' || $isFinished ? number_format((float) $lot['qty_on_hand']) . ' units' : fmt_qty($lot['qty_on_hand'], $lot['base_unit_code']); ?>
                        <li class="<?= $i > 0 ? 'border-top pt-2 mt-2' : '' ?>" id="<?= e($lid) ?>">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <a class="fw-semibold" id="<?= e($lid) ?>-number" <?= nav_attrs($lotUrl) ?>><?= e($lot['lot_number']) ?></a>
                                <span class="fs-12 text-dark" id="<?= e($lid) ?>-qty"><?= e($qty) ?></span>
                            </div>
                            <div class="fs-12 text-muted" id="<?= e($lid) ?>-product"><?= e($isFinished ? $lot['product_name'] . ' — ' . $lot['package_name'] : $lot['item_name']) ?></div>
                            <div class="d-flex align-items-center flex-wrap gap-2 mt-1 fs-11 text-muted">
                                <?php if ($isFinished): ?><span id="<?= e($lid) ?>-batch">Batch <a <?= nav_attrs('/batches/' . (int) $lot['batch_id']) ?>><?= e($lot['batch_number']) ?></a></span><?php endif; ?>
                                <span id="<?= e($lid) ?>-date"><?= $isFinished ? 'Packaged' : 'Received' ?> <?= e(format_date($lot['stock_date'])) ?></span>
                                <?php if ($lot['fifo_rank'] !== null && (int) $lot['fifo_rank'] === 1): ?><?= badge('Pick first', 'success', $lid . '-fifo') ?>
                                <?php elseif ($lot['fifo_rank'] === null): ?><?= status_badge($lot['quality_status'], $lid . '-status') ?><?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if ($board === []): ?>
    <div class="col-12" id="rack-board-empty"><div class="card"><div class="card-body text-center py-5 text-muted"><i class="feather-inbox fs-1 d-block mb-3"></i><?= ($query['q'] ?? '') !== '' ? 'No rack holds a match.' : 'No racks yet. Add a rack to an area such as Packaged goods.' ?></div></div></div>
    <?php endif; ?>
</div>
</div>
