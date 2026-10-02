<?php /** @var array $lot  @var array $balances  @var array $attributes  @var array $certificates  @var array $decisions  @var array $movements  @var array $user */
$id = (int) $lot['id'];
$kind = $lot['item_class'] === 'fruit' ? 'fruit' : 'default';
$canQuality = user_can($user, 'quality');
$actions = nav_button('lot-view-edit-btn', '/lots/' . $id . '/edit', 'Edit', 'feather-edit', 'btn btn-light-brand')
    . ($canQuality ? nav_button('lot-view-header-release-btn', '/lots/' . $id . '/release', 'Release decision', 'feather-check-circle') : '');
?>
<?= view('shared/page-header.php', ['title' => $lot['lot_number'], 'screen' => 'lot-view', 'crumbs' => ['Receiving' => null, 'Lots' => '/lots/', $lot['lot_number'] => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="lot-view-content">
    <div class="row">
        <div class="col-xxl-4 col-xl-6">
            <div class="card" id="lot-view-summary">
                <div class="card-body">
                    <div class="mb-4 d-flex align-items-center justify-content-between">
                        <div><h5 class="fw-bold mb-1"><?= e($lot['lot_number']) ?></h5><div class="fs-12 text-muted"><?= e($lot['item_code'] . ' — ' . $lot['item_name']) ?></div></div>
                        <?= status_badge($lot['quality_status'], 'lot-view-status') ?>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-package"></i>On hand</span><span id="lot-view-on-hand" class="fw-semibold"><?= fmt_qty_html($lot['qty_on_hand'], $lot['base_unit_code'], 1, $kind) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-truck"></i>Supplier</span><span id="lot-view-supplier"><?= e($lot['supplier_name'] ?? '—') ?><?= $lot['supplier_lot_number'] ? ' <small class="text-muted">(' . e($lot['supplier_lot_number']) . ')</small>' : '' ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-calendar"></i>Received</span><span id="lot-view-received-on"><?= e(format_date($lot['received_on'] ?? $lot['produced_on'])) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-alert-triangle"></i>Expires</span><span id="lot-view-expires-on" class="<?= $lot['expiring_soon'] ? 'text-danger fw-semibold' : '' ?>"><?= e(format_date($lot['expires_on']) ?: '—') ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-dollar-sign"></i>Cost</span><span id="lot-view-cost"><?= e(fmt_unit_cost($lot['unit_cost_base'], $lot['base_unit_code'], $kind)) ?></span></li>
                        <li class="hstack justify-content-between mb-0"><span class="text-muted fw-medium hstack gap-3"><i class="feather-git-commit"></i>Source</span><span id="lot-view-source"><?php if ($lot['source_kind'] === 'receipt_line'): ?><a <?= nav_attrs('/receipts/' . (int) $receiptId) ?>><?= e($receiptNumber) ?></a><?php else: ?><?= e(humanize($lot['source_kind'])) ?><?php endif; ?></span></li>
                    </ul>
                    <?php if ($lot['notes']): ?><p class="mt-4 mb-0 text-muted fs-12" id="lot-view-notes"><?= e($lot['notes']) ?></p><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xxl-8 col-xl-6">
            <div class="card border-top-0" id="lot-view-tabs-card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="lot-view-tabs" role="tablist">
                        <?php foreach (['overview' => 'Overview', 'attributes' => 'Attributes', 'certificates' => 'Certificates', 'releases' => 'Release history', 'movements' => 'Movements'] as $tab => $label): ?>
                            <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="lot-view-tab-<?= e($tab) ?>" class="nav-link<?= $tab === $activeTab ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#lot-view-pane-<?= e($tab) ?>" role="tab"><?= e($label) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade<?= $activeTab === 'overview' ? ' show active' : '' ?>" id="lot-view-pane-overview" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="lot-view-balances-table">
                                <thead class="thead-light"><tr><th>Location</th><th>Tax state</th><th>On hand</th><th>Allocated</th><th>Available</th></tr></thead>
                                <tbody>
                                <?php foreach ($balances as $balance): ?>
                                    <tr id="lot-balance-row-<?= e($balance['location_id']) ?>">
                                        <td><?= e($balance['location_name']) ?></td>
                                        <td><?= badge(humanize($balance['tax_state']), $balance['tax_state'] === 'bonded' ? 'info' : 'warning') ?></td>
                                        <td class="fw-semibold"><?= fmt_qty_html($balance['qty_on_hand'], $lot['base_unit_code'], 1, $kind) ?></td>
                                        <td><?= fmt_qty_html($balance['qty_allocated'], $lot['base_unit_code'], 1, $kind) ?></td>
                                        <td><?= fmt_qty_html($balance['qty_available'], $lot['base_unit_code'], 1, $kind) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($balances === []): ?><tr><td colspan="5" class="text-center text-muted py-4">No stock left in this lot.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade<?= $activeTab === 'attributes' ? ' show active' : '' ?>" id="lot-view-pane-attributes" role="tabpanel">
                        <?= view('lots/partials/attributes-tab.php', ['lot' => $lot, 'attributes' => $attributes, 'errors' => [], 'canEdit' => $canQuality]) ?>
                    </div>
                    <div class="tab-pane fade<?= $activeTab === 'certificates' ? ' show active' : '' ?>" id="lot-view-pane-certificates" role="tabpanel">
                        <?= view('lots/partials/certificates-tab.php', ['lot' => $lot, 'certificates' => $certificates, 'canEdit' => $canQuality]) ?>
                    </div>
                    <div class="tab-pane fade<?= $activeTab === 'releases' ? ' show active' : '' ?>" id="lot-view-pane-releases" role="tabpanel">
                        <?= view('lots/partials/releases-tab.php', ['lot' => $lot, 'decisions' => $decisions, 'canRelease' => $canQuality]) ?>
                    </div>
                    <div class="tab-pane fade<?= $activeTab === 'movements' ? ' show active' : '' ?>" id="lot-view-pane-movements" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="lot-view-movements-table">
                                <thead class="thead-light"><tr><th>Occurred</th><th>Type</th><th>Location</th><th>Quantity</th><th>Reason</th><th>Reference</th><th>Actor</th></tr></thead>
                                <tbody>
                                <?php foreach (($movements ?? []) as $movement): $mid = (int) $movement['id']; $qty = (float) $movement['qty_base'];
                                    $refUrl = isset(INVENTORY_REFERENCE_URLS[$movement['reference_kind']]) ? INVENTORY_REFERENCE_URLS[$movement['reference_kind']] . (int) $movement['reference_id'] : null;
                                    $refLabel = humanize($movement['reference_kind']) . ' #' . (int) $movement['reference_id']; ?>
                                    <tr id="lot-movement-row-<?= e($mid) ?>">
                                        <td id="lot-movement-row-<?= e($mid) ?>-occurred"><?= e(format_datetime($movement['occurred_at'])) ?></td>
                                        <td id="lot-movement-row-<?= e($mid) ?>-type"><?= badge(INVENTORY_TXN_TYPES[$movement['txn_type']] ?? humanize($movement['txn_type']), INVENTORY_TXN_COLORS[$movement['txn_type']] ?? 'secondary') ?></td>
                                        <td id="lot-movement-row-<?= e($mid) ?>-location"><?= e($movement['location_name']) ?></td>
                                        <td id="lot-movement-row-<?= e($mid) ?>-qty" class="fw-semibold <?= $qty < 0 ? 'text-danger' : '' ?>"><?= $qty > 0 ? '+' : '' ?><?= fmt_qty_html($qty, $movement['base_unit_code'], 1, $kind) ?></td>
                                        <td id="lot-movement-row-<?= e($mid) ?>-reason"><?= e($movement['reason_code'] ?? '') ?></td>
                                        <td id="lot-movement-row-<?= e($mid) ?>-reference"><?php if ($refUrl): ?><a <?= nav_attrs($refUrl) ?>><?= e($refLabel) ?></a><?php else: ?><?= e($refLabel) ?><?php endif; ?></td>
                                        <td id="lot-movement-row-<?= e($mid) ?>-actor"><?= e($movement['actor_name'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (($movements ?? []) === []): ?><tr id="lot-view-movements-empty"><td colspan="7" class="text-center text-muted py-4">No movements recorded for this lot.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-3 text-end border-top"><a class="btn btn-sm btn-light-brand" id="lot-view-movements-link" <?= nav_attrs('/inventory/movements?lot_number=' . rawurlencode($lot['lot_number'])) ?>>Open in movements</a></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
