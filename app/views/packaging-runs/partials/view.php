<?php /** @var array $run  @var array $materials  @var array $readback  @var array $user */
$id = (int) $run['id'];
$status = $run['status'];
$canEdit = user_can($user, 'production');
$actions = '';
if ($status === 'draft' && $canEdit) {
    $actions .= nav_button('packaging-run-view-edit-btn', '/packaging-runs/' . $id . '/edit', 'Edit', 'feather-edit', 'btn btn-light-brand');
    $actions .= '<button type="button" class="btn btn-light-brand" id="packaging-run-view-delete-btn" hx-post="/packaging-runs/' . e($id) . '/delete" hx-target="#page-content" hx-swap="innerHTML" hx-confirm="Delete draft ' . e($run['number']) . '?"><i class="feather-trash-2 me-2"></i><span>Delete</span></button>';
    $actions .= '<button type="button" class="btn btn-primary" id="packaging-run-view-post-btn" hx-post="/packaging-runs/' . e($id) . '/post" hx-target="#page-content" hx-swap="innerHTML"><i class="feather-check-circle me-2"></i><span>Post packaging run</span></button>';
}
if ($status === 'posted' && $canEdit) {
    $actions .= '<button type="button" class="btn btn-light-brand" id="packaging-run-view-reverse-btn" hx-post="/packaging-runs/' . e($id) . '/reverse" hx-target="#page-content" hx-swap="innerHTML" hx-confirm="Reverse ' . e($run['number']) . '? This returns the volume to the batch and voids the finished lot."><i class="feather-rotate-ccw me-2"></i><span>Reverse</span></button>';
}
$posted = $status === 'posted';
$taxClass = $posted ? $run['finished_tax_class'] : $readback['tax_class'];
$lossPct = $posted && (float) $run['volume_in_l'] > 0 ? (float) $run['loss_l'] / (float) $run['volume_in_l'] * 100 : $readback['loss_pct'];
$expected = (float) $run['expected_loss_pct'];
$lossL = $posted ? $run['loss_l'] : $readback['loss_l'];
$volumeOut = $posted ? $run['volume_out_l'] : $readback['volume_out_l'];
$exceptional = $lossPct !== null && $lossPct > $expected;
$tabs = ['materials' => 'Materials', 'loss' => 'Loss and yield', 'lot' => 'Finished lot'];
?>
<?php $actions = nav_button('packaging-run-view-schedule-btn', '/schedule/?subject=packaging_run:' . (int) $run['id'], 'Schedule', 'feather-calendar', 'btn btn-light-brand') . (isset($user) && user_can($user, 'production') ? nav_button('packaging-run-view-reserve-btn', '/reservations/new?subject_kind=packaging_run&subject_id=' . (int) $run['id'], 'Reserve', 'feather-bookmark', 'btn btn-light-brand') : '') . ($actions ?? ''); ?>
<?= view('shared/page-header.php', ['title' => $run['number'], 'screen' => 'packaging-run-view', 'crumbs' => ['Packaging' => null, 'Packaging runs' => '/packaging-runs/', $run['number'] => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="packaging-run-view-content">
    <div class="row">
        <div class="col-xxl-4 col-xl-6">
            <div class="card" id="packaging-run-view-summary">
                <div class="card-body">
                    <div class="mb-4 d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold mb-0"><?= e($run['number']) ?></h5>
                        <?= status_badge($status, 'packaging-run-view-status') ?>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-layers"></i>Batch</span><span id="packaging-run-view-batch"><a <?= nav_attrs('/batches/' . (int) $run['batch_id']) ?>><?= e($run['batch_number']) ?></a> <small class="text-muted"><?= e($run['product_name']) ?></small></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-package"></i>Package</span><span id="packaging-run-view-package"><?= e($run['configuration_name']) ?> <small class="text-muted"><?= e(humanize($run['package_kind'])) ?></small></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-calendar"></i>Run date</span><span id="packaging-run-view-run-on"><?= e(format_date($run['run_on'])) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-database"></i>Source vessel</span><span id="packaging-run-view-vessel"><?= e($run['source_vessel_name'] ?? '—') ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-map-pin"></i>Output</span><span id="packaging-run-view-location"><?= e($run['output_location_name']) ?> <?= badge(humanize($run['output_tax_state']), $run['output_tax_state'] === 'bonded' ? 'info' : 'warning') ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-droplet"></i>Volume in</span><span id="packaging-run-view-volume-in"><?= fmt_qty_html($run['volume_in_l'], 'L', 2) ?: '—' ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-box"></i>Units out</span><span id="packaging-run-view-units-out" class="fw-semibold"><?= e($run['units_out'] ?? '—') ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-percent"></i>ABV / CO2</span><span id="packaging-run-view-abv-co2"><?= $run['abv_at_packaging'] !== null ? e(number_format((float) $run['abv_at_packaging'], 2)) . '%' : '—' ?> / <?= $run['co2_g_100ml'] !== null ? e(number_format((float) $run['co2_g_100ml'], 3)) . ' g/100 mL' : '—' ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-shield"></i>Tax class</span><span id="packaging-run-view-tax-class"><?= $taxClass !== null ? badge(humanize($taxClass), $taxClass === 'hard_cider' ? 'success' : 'warning') . ($posted ? '' : ' <small class="text-muted">derived</small>') : '—' ?></span></li>
                        <li class="hstack justify-content-between mb-0"><span class="text-muted fw-medium hstack gap-3"><i class="feather-check-circle"></i>Posted</span><span id="packaging-run-view-posted"><?= e($run['posted_at'] ? $run['posted_by_name'] . ', ' . format_datetime($run['posted_at']) : 'Not yet') ?></span></li>
                    </ul>
                    <?php if ($run['notes']): ?><p class="mt-4 mb-0 text-muted fs-12" id="packaging-run-view-notes"><?= e($run['notes']) ?></p><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xxl-8 col-xl-6">
            <div class="card border-top-0" id="packaging-run-view-tabs-card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="packaging-run-view-tabs" role="tablist">
                        <?php foreach ($tabs as $tab => $label): ?>
                            <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="packaging-run-view-tab-<?= e($tab) ?>" class="nav-link<?= $tab === 'materials' ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#packaging-run-view-pane-<?= e($tab) ?>" role="tab"><?= e($label) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="packaging-run-view-pane-materials" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="packaging-run-view-materials-table">
                                <thead class="thead-light"><tr><th>Item</th><th>Mode</th><th>Quantity</th><th>Lot</th></tr></thead>
                                <tbody>
                                <?php foreach ($materials as $material): $mid = (int) $material['id']; ?>
                                    <tr id="packaging-run-material-row-<?= e($mid) ?>">
                                        <td id="packaging-run-material-row-<?= e($mid) ?>-item"><?= e($material['item_code']) ?> <small class="text-muted"><?= e($material['item_name']) ?></small></td>
                                        <td id="packaging-run-material-row-<?= e($mid) ?>-mode"><?= badge(humanize($material['mode']), $material['mode'] === 'explicit' ? 'info' : 'secondary') ?></td>
                                        <td id="packaging-run-material-row-<?= e($mid) ?>-qty"><?= fmt_qty_html($material['qty_base'], $material['base_unit_code'], 2, $material['item_class'] === 'fruit' ? 'fruit' : 'default') ?></td>
                                        <td id="packaging-run-material-row-<?= e($mid) ?>-lot"><?php if ($material['lot_id']): ?><a <?= nav_attrs('/lots/' . (int) $material['lot_id']) ?>><?= e($material['lot_number']) ?></a><?php else: ?><span class="text-muted"><?= $material['mode'] === 'backflush' ? 'Chosen on posting' : '—' ?></span><?php endif; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($materials === []): ?><tr><td colspan="4" class="text-center text-muted py-4">No materials on this run.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade p-4" id="packaging-run-view-pane-loss" role="tabpanel">
                        <?= detail_row('packaging-run-view-loss-volume-in', 'Volume in', fmt_qty_html($run['volume_in_l'], 'L', 2) ?: '—') ?>
                        <?= detail_row('packaging-run-view-loss-volume-out', $posted ? 'Volume out' : 'Volume out (projected)', $volumeOut !== null ? fmt_qty_html($volumeOut, 'L', 2) : '—') ?>
                        <?= detail_row('packaging-run-view-loss-actual', $posted ? 'Loss' : 'Loss (projected)', $lossL !== null ? fmt_qty_html($lossL, 'L', 2) . ' <small class="' . ($exceptional ? 'text-danger' : 'text-muted') . '">' . e(number_format((float) $lossPct, 2)) . '%</small>' : '—') ?>
                        <?= detail_row('packaging-run-view-loss-expected', 'Expected loss', e(number_format($expected, 2)) . '%') ?>
                        <?= detail_row('packaging-run-view-loss-class', 'Classification', $lossL === null ? '—' : ($lossL <= 0 ? badge('No loss', 'success') : ($exceptional ? badge('Exceptional', 'danger') : badge('Expected', 'success'))), true) ?>
                    </div>
                    <div class="tab-pane fade p-4" id="packaging-run-view-pane-lot" role="tabpanel">
                        <?php if ($run['finished_lot_id']): ?>
                            <?= detail_row('packaging-run-view-lot-number', 'Finished lot', '<a ' . nav_attrs('/finished-lots/' . (int) $run['finished_lot_id']) . '>' . e($run['finished_lot_number']) . '</a>') ?>
                            <?= detail_row('packaging-run-view-lot-item', 'Item', e($run['finished_item_code'] . ' — ' . $run['finished_item_name'])) ?>
                            <?= detail_row('packaging-run-view-lot-tax-class', 'Tax class', badge(humanize($run['finished_tax_class']), $run['finished_tax_class'] === 'hard_cider' ? 'success' : 'warning') . ' <small class="text-muted">' . e($run['tax_class_source']) . '</small>', true) ?>
                        <?php else: ?>
                            <p class="text-muted mb-0" id="packaging-run-view-lot-empty">The finished lot is created when this run is posted.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
