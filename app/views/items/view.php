<?php /** @var array $item  @var array $units  @var array $suppliers  @var ?array $stock  @var array $balances  @var array $unitInput  @var array $unitErrors  @var bool $canEdit */
$id = (int) $item['id'];
$base = $item['base_unit_code'];
$kind = items_unit_kind($item['item_class']);
$actions = nav_button('item-view-back-btn', '/items/', 'Items', 'feather-arrow-left', 'btn btn-light-brand')
    . ($canEdit ? nav_button('item-view-edit-btn', '/items/' . $id . '/edit', 'Edit Item', 'feather-edit') : '');
$tabs = ['overview' => 'Overview', 'units' => 'Alternate units', 'suppliers' => 'Suppliers', 'stock' => 'Stock'];
?>
<?= view('shared/page-header.php', ['title' => $item['code'], 'screen' => 'item-view', 'crumbs' => ['Setup' => null, 'Items' => '/items/', $item['code'] => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="item-view-content">
    <div class="row">
        <div class="col-xxl-4 col-xl-6">
            <div class="card stretch stretch-full" id="item-view-summary">
                <div class="card-body">
                    <div class="mb-4">
                        <span class="fs-14 fw-bold d-block" id="item-view-code"><?= status_dot($item['active'] ? 'success' : 'secondary') ?><?= e($item['code']) ?></span>
                        <span class="fs-12 fw-normal text-muted d-block" id="item-view-name"><?= e($item['name']) ?></span>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <li class="hstack justify-content-between mb-4" id="item-view-class"><span class="text-muted fw-medium hstack gap-3"><i class="feather-tag"></i>Class</span><?= badge(ITEM_CLASSES[$item['item_class']] ?? $item['item_class'], 'info') ?></li>
                        <li class="hstack justify-content-between mb-4" id="item-view-base-unit"><span class="text-muted fw-medium hstack gap-3"><i class="feather-layers"></i>Base unit</span><span><?= e($base) ?></span></li>
                        <li class="hstack justify-content-between mb-4" id="item-view-receipt-status"><span class="text-muted fw-medium hstack gap-3"><i class="feather-inbox"></i>Receipt status</span><?= status_badge($item['default_receipt_status']) ?></li>
                        <li class="hstack justify-content-between mb-0" id="item-view-status"><span class="text-muted fw-medium hstack gap-3"><i class="feather-activity"></i>Status</span><?= status_badge($item['active'] ? 'active' : 'inactive') ?></li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="col-xxl-8 col-xl-6">
            <div class="card border-top-0" id="item-view-card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="item-view-tabs" role="tablist">
                        <?php foreach ($tabs as $key => $label): ?>
                        <li class="nav-item flex-fill border-top" role="presentation">
                            <a href="javascript:void(0);" id="item-view-tab-<?= e($key) ?>" class="nav-link<?= $key === 'overview' ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#item-view-pane-<?= e($key) ?>" role="tab"><?= e($label) ?></a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active p-4" id="item-view-pane-overview" role="tabpanel">
                        <div class="mb-4"><h5 class="fw-bold mb-0">Item details:</h5></div>
                        <?= detail_row('item-view-detail-lot-controlled', 'Lot controlled', e(yes_no($item['lot_controlled']))) ?>
                        <?= detail_row('item-view-detail-catch-weight', 'Catch weight', e(yes_no($item['catch_weight']))) ?>
                        <?= detail_row('item-view-detail-shelf-life', 'Shelf life', $item['shelf_life_days'] === null ? '' : e($item['shelf_life_days'] . ' days')) ?>
                        <?= detail_row('item-view-detail-consumption-mode', 'Consumption mode', e(ITEM_CONSUMPTION_MODES[$item['consumption_mode']] ?? $item['consumption_mode'])) ?>
                        <?= detail_row('item-view-detail-costing-method', 'Costing method', e(ITEM_COSTING_METHODS[$item['costing_method']] ?? $item['costing_method'])) ?>
                        <?= detail_row('item-view-detail-standard-cost', 'Standard cost', e(fmt_unit_cost($item['standard_cost_per_base'], $base, $kind))) ?>
                        <?= detail_row('item-view-detail-ttb-category', 'TTB material category', e(ITEM_TTB_CATEGORIES[$item['ttb_material_category']] ?? $item['ttb_material_category'])) ?>
                        <?= detail_row('item-view-detail-units-per-case', 'Units per case', e($item['units_per_case'])) ?>
                        <?= detail_row('item-view-detail-reorder-point', 'Reorder point', fmt_qty_html($item['reorder_point_base'], $base, 1, $kind)) ?>
                        <?= detail_row('item-view-detail-min-qty', 'Minimum quantity', fmt_qty_html($item['min_qty_base'], $base, 1, $kind)) ?>
                        <?= detail_row('item-view-detail-max-qty', 'Maximum quantity', fmt_qty_html($item['max_qty_base'], $base, 1, $kind)) ?>
                        <?= detail_row('item-view-detail-notes', 'Notes', nl2br(e($item['notes'])), true) ?>
                    </div>
                    <div class="tab-pane fade p-4" id="item-view-pane-units" role="tabpanel">
                        <?= view('items/units-tab.php', ['item' => $item, 'units' => $units, 'unitInput' => $unitInput, 'unitErrors' => $unitErrors, 'canEdit' => $canEdit]) ?>
                    </div>
                    <div class="tab-pane fade p-4" id="item-view-pane-suppliers" role="tabpanel">
                        <?= view('items/suppliers-tab.php', ['item' => $item, 'suppliers' => $suppliers]) ?>
                    </div>
                    <div class="tab-pane fade" id="item-view-pane-stock" role="tabpanel">
                        <?php if ($stock !== null): ?>
                        <div class="p-4 border-bottom hstack gap-4 flex-wrap" id="item-view-stock-summary">
                            <span id="item-view-stock-on-hand"><span class="text-muted">On hand</span> <span class="fw-semibold"><?= fmt_qty_html($stock['qty_on_hand'], $base, 1, $kind) ?></span></span>
                            <span id="item-view-stock-allocated"><span class="text-muted">Allocated</span> <span class="fw-semibold"><?= fmt_qty_html($stock['qty_allocated'], $base, 1, $kind) ?></span></span>
                            <span id="item-view-stock-available"><span class="text-muted">Available</span> <span class="fw-semibold"><?= fmt_qty_html($stock['qty_available'], $base, 1, $kind) ?></span></span>
                            <span id="item-view-stock-on-order"><span class="text-muted">On order</span> <span class="fw-semibold"><?= fmt_qty_html($stock['qty_on_order'], $base, 1, $kind) ?></span></span>
                            <?php if ($stock['below_reorder_point']): ?><?= badge('Below reorder point', 'warning', 'item-view-stock-reorder-flag') ?><?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="item-view-stock-table">
                                <thead class="thead-light"><tr><th>Lot</th><th>Location</th><th>On hand</th><th>Allocated</th><th>Available</th><th>Expires</th></tr></thead>
                                <tbody>
                                <?php foreach ($balances as $balance): $bkey = (int) $balance['lot_id'] . '-' . (int) $balance['location_id']; ?>
                                    <tr id="item-stock-row-<?= e($bkey) ?>">
                                        <td id="item-stock-row-<?= e($bkey) ?>-lot"><a <?= nav_attrs('/lots/' . (int) $balance['lot_id']) ?>><?= status_dot(status_color($balance['quality_status'])) ?><?= e($balance['lot_number']) ?></a></td>
                                        <td id="item-stock-row-<?= e($bkey) ?>-location"><?= e($balance['location_name']) ?> <small class="text-muted"><?= e(humanize($balance['tax_state'])) ?></small></td>
                                        <td id="item-stock-row-<?= e($bkey) ?>-on-hand" class="fw-semibold"><?= fmt_qty_html($balance['qty_on_hand'], $base, 1, $kind) ?></td>
                                        <td id="item-stock-row-<?= e($bkey) ?>-allocated"><?= fmt_qty_html($balance['qty_allocated'], $base, 1, $kind) ?></td>
                                        <td id="item-stock-row-<?= e($bkey) ?>-available"><?= fmt_qty_html($balance['qty_available'], $base, 1, $kind) ?></td>
                                        <td id="item-stock-row-<?= e($bkey) ?>-expires"><?= e(format_date($balance['expires_on'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($balances === []): ?><tr id="item-view-stock-empty"><td colspan="6" class="text-center text-muted py-4">No stock on hand.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
