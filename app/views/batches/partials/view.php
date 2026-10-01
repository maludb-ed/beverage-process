<?php /** @var array $batch  @var array $vessels  @var array $readings  @var array $consumptions  @var array $transfers  @var array $losses  @var array $lineage  @var array $trace  @var ?array $cost  @var array $yields  @var array $user  @var string $activeTab */
$id = (int) $batch['id'];
$base = '/batches/' . $id;
$active = $batch['status'] === 'active';
$canAct = $active && user_can($user, 'production');
$actions = '';
if ($canAct) {
    $vesselName = $vessels[0]['vessel_name'] ?? '';
    $item = static fn(string $action, string $url, string $label, string $icon) => '<a id="batch-view-' . e($action) . '-btn" class="dropdown-item" ' . nav_attrs($url) . '><i class="' . e($icon) . ' me-3"></i><span>' . e($label) . '</span></a>';
    $actions .= nav_button('batch-view-addition-btn', $base . '/additions/new', 'Addition', 'feather-plus-circle', 'btn btn-light-brand');
    $actions .= nav_button('batch-view-stage-btn', $base . '/stage', 'Move stage', 'feather-skip-forward', 'btn btn-light-brand');
    $actions .= nav_button('batch-view-transfer-btn', $base . '/transfer', 'Transfer', 'feather-shuffle', 'btn btn-light-brand');
    if (user_can($user, 'quality')) {   // slice 8
        $actions .= nav_button('batch-view-release-btn', $base . '/release', 'Release', 'feather-check-circle', 'btn btn-light-brand');
    }
    // slice 7: packaging takes the batch number as a prefill
    $actions .= nav_button('batch-view-package-btn', '/packaging-runs/new?batch=' . rawurlencode((string) $batch['number']), 'Package', 'feather-box', 'btn btn-light-brand');
    $actions .= '<div class="dropdown"><a href="javascript:void(0);" id="batch-view-more-btn" class="btn btn-icon btn-light-brand" data-bs-toggle="dropdown" data-bs-offset="0, 10" title="More actions"><i class="feather-more-horizontal"></i></a>'
        . '<div class="dropdown-menu dropdown-menu-end" id="batch-view-more-menu">'
        . $item('split', $base . '/split', 'Split', 'feather-git-branch')
        . $item('blend', '/batches/blend' . query_string(['vessel' => $vesselName]), 'Blend', 'feather-git-merge')
        . $item('loss', $base . '/losses/new', 'Loss', 'feather-minus-circle')
        . $item('harvest', $base . '/yeast/new', 'Harvest yeast', 'feather-download')
        . $item('edit', $base . '/edit', 'Edit', 'feather-edit')
        . '<div class="dropdown-divider"></div>'
        . $item('dump', $base . '/dump', 'Dump batch', 'feather-trash-2')
        . '</div></div>';
    $actions .= nav_button('batch-view-reading-btn', $base . '/readings/new', 'Reading', 'feather-activity');
}
$li = static fn(string $key, string $icon, string $label, string $html, bool $last = false) => '<li class="hstack justify-content-between gap-3 ' . ($last ? 'mb-0' : 'mb-4') . '"><span class="text-muted fw-medium hstack gap-3 text-nowrap"><i class="' . e($icon) . '"></i>' . e($label) . '</span><span id="batch-view-' . e($key) . '" class="text-end">' . $html . '</span></li>';
$vesselHtml = $vessels === [] ? '<span class="text-muted">None</span>' : implode(', ', array_map(static fn($v) => e($v['vessel_name']) . ' <small class="text-muted">' . e(fmt_qty($v['volume_l'], 'L')) . '</small>', $vessels));
$taxHtml = batches_tax_class_badge($batch['tax_class']) . ($batch['tax_class_override'] !== null ? ' <small class="text-muted">override</small>' : '');
$tabs = ['readings' => 'Readings', 'consumptions' => 'Consumptions', 'transfers' => 'Transfers', 'losses' => 'Losses', 'lineage' => 'Lineage', 'cost' => 'Cost'];
?>
<?= view('shared/page-header.php', ['title' => $batch['number'], 'screen' => 'batch-view', 'crumbs' => ['Production' => null, 'Batches' => '/batches/', $batch['number'] => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="batch-view-content">
    <div class="row">
        <div class="col-xxl-4 col-xl-6">
            <div class="card stretch stretch-full" id="batch-view-summary">
                <div class="card-body">
                    <div class="mb-4 d-flex align-items-center justify-content-between">
                        <div><h5 class="fw-bold mb-1"><?= e($batch['number']) ?></h5><div class="fs-12 text-muted"><?= e(humanize($batch['origin_kind'])) ?></div></div>
                        <?= batches_status_badge($batch['status'], 'batch-view-status') ?>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <?= $li('product', 'feather-box', 'Product', '<a ' . nav_attrs('/products/' . (int) $batch['product_id']) . '>' . e($batch['product_name']) . '</a>') ?>
                        <?= $li('recipe', 'feather-book-open', 'Recipe', $batch['recipe_version_id'] ? '<a ' . nav_attrs('/recipes/' . (int) $batch['recipe_version_id']) . '>v' . e($batch['version_no']) . '</a>' : '<span class="text-muted">None</span>') ?>
                        <?= $li('production-order', 'feather-clipboard', 'Order', $batch['production_order_id'] ? '<a ' . nav_attrs('/production-orders/' . (int) $batch['production_order_id']) . '>' . e($batch['po_number']) . '</a>' : '<span class="text-muted">None</span>') ?>
                        <?= $li('stage', 'feather-layers', 'Stage', badge($batch['stage_name'], 'info') . ' <small class="text-muted">' . e((int) $batch['days_in_stage']) . ' d</small>') ?>
                        <?= $li('vessels', 'feather-database', 'Vessel', $vesselHtml) ?>
                        <?= $li('volume', 'feather-droplet', 'Volume', '<span class="fw-semibold">' . fmt_qty_html($batch['current_volume_l'], 'L', 1) . '</span>') ?>
                        <?= $li('started', 'feather-clock', 'Started', e(format_datetime($batch['started_at']))) ?>
                        <?= $li('fruit-share', 'feather-pie-chart', 'Fruit share', $batch['fruit_share_pct'] !== null ? e(number_format((float) $batch['fruit_share_pct'], 1)) . '%' : '—') ?>
                        <?= $li('tax-class', 'feather-file-text', 'Tax class', $taxHtml, true) ?>
                    </ul>
                    <?php if ($batch['notes']): ?><p class="mt-4 mb-0 text-muted fs-12" id="batch-view-notes"><?= nl2br(e($batch['notes'])) ?></p><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xxl-8 col-xl-6">
            <div class="card border-top-0" id="batch-view-tabs-card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="batch-view-tabs" role="tablist">
                        <?php foreach ($tabs as $tab => $label): ?>
                            <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="batch-view-tab-<?= e($tab) ?>" class="nav-link<?= $tab === $activeTab ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#batch-view-pane-<?= e($tab) ?>" role="tab"><?= e($label) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="tab-content">
                    <?php foreach ($tabs as $tab => $label): ?>
                    <div class="tab-pane fade<?= $tab === $activeTab ? ' show active' : '' ?>" id="batch-view-pane-<?= e($tab) ?>" role="tabpanel">
                        <?= view('batches/partials/tab-' . $tab . '.php', ['batch' => $batch, 'readings' => $readings, 'consumptions' => $consumptions, 'transfers' => $transfers,
                            'losses' => $losses, 'lineage' => $lineage, 'trace' => $trace, 'cost' => $cost, 'yields' => $yields, 'canApprove' => user_can($user, 'compliance')]) ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
