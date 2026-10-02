<?php /** @var array $lot  @var array $balances  @var array $removals  @var array $kegs  @var array $user  @var ?string $derived  @var array $limits  @var array $reasons  @var ?int $defaultReason */
$id = (int) $lot['lot_id'];
$tabs = ['stock' => 'Stock', 'removals' => 'Removals', 'kegs' => 'Kegs'];
?>
<?= view('shared/page-header.php', ['title' => $lot['lot_number'], 'screen' => 'finished-lot-view', 'crumbs' => ['Packaging' => null, 'Finished goods' => '/finished-lots/', $lot['lot_number'] => null]]) ?>
<div class="main-content" id="finished-lot-view-content">
    <div class="row">
        <div class="col-xxl-4 col-xl-6">
            <div class="card" id="finished-lot-view-summary">
                <div class="card-body">
                    <div class="mb-4 d-flex align-items-center justify-content-between">
                        <div><h5 class="fw-bold mb-1"><?= e($lot['lot_number']) ?></h5><div class="fs-12 text-muted"><?= e($lot['item_code'] . ' — ' . $lot['item_name']) ?></div></div>
                        <?= status_badge($lot['quality_status'], 'finished-lot-view-status') ?>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-package"></i>On hand</span><span id="finished-lot-view-on-hand" class="fw-semibold"><?= e(number_format((float) $lot['units_on_hand'])) ?> of <?= e(number_format((int) $lot['units_packaged'])) ?> units</span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-box"></i>Product</span><span id="finished-lot-view-product"><?= e($lot['product_name']) ?> <small class="text-muted"><?= e($lot['package_name']) ?></small></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-layers"></i>Batch</span><span id="finished-lot-view-batch"><a <?= nav_attrs('/batches/' . (int) $lot['batch_id']) ?>><?= e($lot['batch_number']) ?></a></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-git-commit"></i>Packaging run</span><span id="finished-lot-view-run"><a <?= nav_attrs('/packaging-runs/' . (int) $lot['packaging_run_id']) ?>><?= e($lot['run_number']) ?></a></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-calendar"></i>Packaged</span><span id="finished-lot-view-packaged-on"><?= e(format_date($lot['packaged_on'])) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-alert-triangle"></i>Best before</span><span id="finished-lot-view-best-before"><?= e($lot['best_before_on'] ? format_date($lot['best_before_on']) : '—') ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-droplet"></i>Unit volume</span><span id="finished-lot-view-unit-volume"><?= fmt_qty_html($lot['unit_volume_l'], 'L', 3) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-dollar-sign"></i>Unit cost</span><span id="finished-lot-view-unit-cost">$<?= e(number_format((float) $lot['unit_cost_base'], 4)) ?></span></li>
                        <li class="hstack justify-content-between mb-0"><span class="text-muted fw-medium hstack gap-3"><i class="feather-award"></i>Label approval</span><span id="finished-lot-view-label"><?= $lot['label_approval_id'] ? e(($lot['label_reference'] ?: 'Approved')) : '<span class="text-muted">None</span>' ?></span></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-xxl-8 col-xl-6">
            <?= view('finished-lots/partials/tax-class-card.php', ['lot' => $lot, 'derived' => $derived, 'limits' => $limits, 'reasons' => $reasons, 'defaultReason' => $defaultReason, 'canOverride' => user_can($user, 'compliance'), 'errors' => [], 'input' => [], 'notice' => null]) ?>
            <div class="card border-top-0" id="finished-lot-view-tabs-card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="finished-lot-view-tabs" role="tablist">
                        <?php foreach ($tabs as $tab => $label): ?>
                            <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="finished-lot-view-tab-<?= e($tab) ?>" class="nav-link<?= $tab === 'stock' ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#finished-lot-view-pane-<?= e($tab) ?>" role="tab"><?= e($label) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="finished-lot-view-pane-stock" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="finished-lot-view-balances-table">
                                <thead class="thead-light"><tr><th>Location</th><th>Tax state</th><th>On hand</th><th>Available</th></tr></thead>
                                <tbody>
                                <?php foreach ($balances as $balance): ?>
                                    <tr id="finished-lot-balance-row-<?= e($balance['location_id']) ?>">
                                        <td><?= e($balance['location_name']) ?></td>
                                        <td><?= badge(humanize($balance['tax_state']), $balance['tax_state'] === 'bonded' ? 'info' : 'warning') ?></td>
                                        <td class="fw-semibold"><?= e(number_format((float) $balance['qty_on_hand'])) ?></td>
                                        <td><?= e(number_format((float) $balance['qty_available'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($balances === []): ?><tr><td colspan="4" class="text-center text-muted py-4">No stock left in this lot.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="finished-lot-view-pane-removals" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="finished-lot-view-removals-table">
                                <thead class="thead-light"><tr><th>Removal</th><th>Date</th><th>Destination</th><th>Customer</th><th>Units</th><th>Keg</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php foreach ($removals as $removal): ?>
                                    <tr id="finished-lot-removal-row-<?= e($removal['id']) ?>">
                                        <td><a <?= nav_attrs('/removals/' . (int) $removal['removal_id']) ?>><?= e($removal['number']) ?></a></td>
                                        <td><?= e(format_date($removal['removed_at'])) ?></td>
                                        <td><?= e(humanize($removal['destination_kind'])) ?></td>
                                        <td><?= e($removal['customer_name'] ?? '—') ?></td>
                                        <td><?= e($removal['units']) ?></td>
                                        <td><?= e($removal['keg_serial'] ?? '—') ?></td>
                                        <td><?= status_badge($removal['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($removals === []): ?><tr><td colspan="7" class="text-center text-muted py-4">Nothing has left from this lot.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="finished-lot-view-pane-kegs" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="finished-lot-view-kegs-table">
                                <thead class="thead-light"><tr><th>Keg</th><th>State</th><th>Holder</th><th>Days</th></tr></thead>
                                <tbody>
                                <?php foreach ($kegs as $keg): ?>
                                    <tr id="finished-lot-keg-row-<?= e($keg['keg_id']) ?>">
                                        <td><a <?= nav_attrs('/kegs/' . (int) $keg['keg_id']) ?>><?= status_dot(status_color($keg['state'])) ?><?= e($keg['serial']) ?></a></td>
                                        <td><?= status_badge($keg['state']) ?></td>
                                        <td><?= e($keg['holder_name'] ?? '—') ?></td>
                                        <td><?= e($keg['days_since_moved'] ?? '—') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($kegs === []): ?><tr><td colspan="4" class="text-center text-muted py-4">No kegs hold this lot.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
