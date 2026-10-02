<?php /** @var array $receipt  @var array $lines  @var array $user */
$id = (int) $receipt['id'];
$status = $receipt['status'];
$actions = '';
if ($status === 'draft' && user_can($user, 'receiving')) {
    $actions .= nav_button('receipt-view-edit-btn', '/receipts/' . $id . '/edit', 'Edit', 'feather-edit', 'btn btn-light-brand');
    $actions .= '<button type="button" class="btn btn-primary" id="receipt-view-post-btn" hx-post="/receipts/' . e($id) . '/post" hx-target="#page-content" hx-swap="innerHTML"><i class="feather-check-circle me-2"></i><span>Post receipt</span></button>';
}
if ($status === 'posted' && user_can($user, 'receiving')) {
    $actions .= nav_button('receipt-view-putaway-btn', '/receipts/' . $id . '/putaway', 'Putaway', 'feather-move');
}
$fruitUnit = display_unit('kg', 'fruit');
?>
<?= view('shared/page-header.php', ['title' => $receipt['number'], 'screen' => 'receipt-view', 'crumbs' => ['Receiving' => null, 'Receipts' => '/receipts/', $receipt['number'] => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="receipt-view-content">
    <div class="row">
        <div class="col-xxl-4 col-xl-6">
            <div class="card" id="receipt-view-summary">
                <div class="card-body">
                    <div class="mb-4 d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold mb-0"><?= e($receipt['number']) ?></h5>
                        <?= status_badge($status, 'receipt-view-status') ?>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-truck"></i>Supplier</span><span id="receipt-view-supplier"><?= e($receipt['supplier_name']) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-file"></i>Order</span><span id="receipt-view-purchase-order"><?php if ($receipt['purchase_order_id']): ?><a <?= nav_attrs('/purchase-orders/' . (int) $receipt['purchase_order_id']) ?>><?= e($receipt['po_number']) ?></a><?php else: ?>Unplanned<?php endif; ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-clock"></i>Received</span><span id="receipt-view-received-at"><?= e(format_datetime($receipt['received_at'])) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-map-pin"></i>Into</span><span id="receipt-view-location"><?= e($receipt['receiving_location_name']) ?> <?= badge(humanize($receipt['receiving_tax_state']), $receipt['receiving_tax_state'] === 'bonded' ? 'info' : 'warning') ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-file-text"></i>Delivery note</span><span id="receipt-view-delivery-note"><?= e($receipt['delivery_note_ref'] ?: '—') ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-user"></i>Received by</span><span id="receipt-view-received-by"><?= e($receipt['received_by_name']) ?></span></li>
                        <li class="hstack justify-content-between mb-0"><span class="text-muted fw-medium hstack gap-3"><i class="feather-check-circle"></i>Posted</span><span id="receipt-view-posted"><?= e($receipt['posted_at'] ? $receipt['posted_by_name'] . ', ' . format_datetime($receipt['posted_at']) : 'Not yet') ?></span></li>
                    </ul>
                    <?php if ($receipt['notes']): ?><p class="mt-4 mb-0 text-muted fs-12" id="receipt-view-notes"><?= e($receipt['notes']) ?></p><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xxl-8 col-xl-6">
            <div class="card border-top-0" id="receipt-view-tabs-card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="receipt-view-tabs" role="tablist">
                        <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="receipt-view-tab-lines" class="nav-link active" data-bs-toggle="tab" data-bs-target="#receipt-view-pane-lines" role="tab">Lines and lots</a></li>
                        <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="receipt-view-tab-weigh-tags" class="nav-link" data-bs-toggle="tab" data-bs-target="#receipt-view-pane-weigh-tags" role="tab">Weigh tags</a></li>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="receipt-view-pane-lines" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="receipt-view-lines-table">
                                <thead class="thead-light"><tr><th>#</th><th>Item</th><th>Received</th><th>Cost</th><th>Lot</th><th>Discrepancy</th><th>Put away</th></tr></thead>
                                <tbody>
                                <?php foreach ($lines as $line): $lid = (int) $line['id']; $kind = $line['item_class'] === 'fruit' ? 'fruit' : 'default'; ?>
                                    <tr id="receipt-line-row-<?= e($lid) ?>">
                                        <td><?= e($line['line_no']) ?></td>
                                        <td id="receipt-line-row-<?= e($lid) ?>-item"><?= e($line['item_code']) ?> <small class="text-muted"><?= e($line['item_name']) ?></small><?= $line['po_line_no'] ? ' <small class="text-muted">(PO line ' . e($line['po_line_no']) . ')</small>' : '' ?></td>
                                        <td id="receipt-line-row-<?= e($lid) ?>-qty"><?= fmt_qty_html($line['qty_base'], $line['base_unit_code'], 1, $kind) ?> <small class="text-muted">(<?= e(format_qty($line['qty_received'], 2) . ' ' . $line['purchase_unit_code']) ?>)</small></td>
                                        <td id="receipt-line-row-<?= e($lid) ?>-cost"><?= e(fmt_unit_cost($line['unit_cost_base'], $line['base_unit_code'], $kind)) ?></td>
                                        <td id="receipt-line-row-<?= e($lid) ?>-lot"><?php if ($line['lot_id']): ?><a <?= nav_attrs('/lots/' . (int) $line['lot_id']) ?>><?= status_dot(status_color($line['quality_status'])) ?><?= e($line['lot_number']) ?></a><?php else: ?><span class="text-muted">On posting</span><?php endif; ?></td>
                                        <td id="receipt-line-row-<?= e($lid) ?>-discrepancy"><?= $line['discrepancy_kind'] === 'none' ? '<span class="text-muted">None</span>' : badge(humanize($line['discrepancy_kind']), 'danger') . ($line['discrepancy_note'] ? ' <small class="text-muted">' . e($line['discrepancy_note']) . '</small>' : '') ?></td>
                                        <td id="receipt-line-row-<?= e($lid) ?>-putaway"><?= e($line['putaway_location_name'] ?? '—') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($lines === []): ?><tr><td colspan="7" class="text-center text-muted py-4">No lines yet.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="receipt-view-pane-weigh-tags" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="receipt-view-weigh-tags-table">
                                <thead class="thead-light"><tr><th>Line</th><th>Tag</th><th>Gross</th><th>Tare</th><th>Net</th><th>Bins</th><th>Variety</th><th>Orchard / block</th><th>Brix</th></tr></thead>
                                <tbody>
                                <?php $tags = 0; foreach ($lines as $line): if ($line['weigh_tag_id'] === null) { continue; } $tags++; $lid = (int) $line['id']; ?>
                                    <tr id="receipt-weigh-tag-row-<?= e($lid) ?>">
                                        <td><?= e($line['line_no']) ?></td>
                                        <td><?= e($line['tag_number'] ?? '') ?></td>
                                        <td><?= fmt_qty_html($line['gross_kg'], 'kg', 1, 'fruit') ?></td>
                                        <td><?= fmt_qty_html($line['tare_kg'], 'kg', 1, 'fruit') ?></td>
                                        <td class="fw-semibold"><?= fmt_qty_html($line['net_kg'], 'kg', 1, 'fruit') ?></td>
                                        <td><?= e($line['bin_count'] ?? '') ?></td>
                                        <td><?= e($line['variety'] ?? '') ?></td>
                                        <td><?= e(trim(($line['orchard'] ?? '') . ' ' . ($line['block'] ?? ''))) ?></td>
                                        <td><?= e($line['brix_at_receipt'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($tags === 0): ?><tr><td colspan="9" class="text-center text-muted py-4">No fruit on this receipt.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
