<?php /** @var array $order  @var array $lines  @var array $receipts  @var array $user  @var array $shortReasons */
$id = (int) $order['id'];
$status = $order['status'];
$can = static fn(string ...$roles) => user_can($user, ...$roles);
$actions = '';
if ($status === 'draft' && $can('receiving')) {
    $actions .= nav_button('purchase-order-view-edit-btn', '/purchase-orders/' . $id . '/edit', 'Edit', 'feather-edit', 'btn btn-light-brand');
}
if ($status === 'draft' && $can()) {
    $actions .= '<button type="button" class="btn btn-primary" id="purchase-order-view-approve-btn" hx-post="/purchase-orders/' . e($id) . '/approve" hx-target="#page-content" hx-swap="innerHTML"><i class="feather-check-circle me-2"></i><span>Approve</span></button>';
}
if (in_array($status, ['open', 'partial'], true) && $can('receiving')) {
    $actions .= nav_button('purchase-order-view-receive-btn', '/receipts/new?po_number=' . rawurlencode($order['number']), 'Receive', 'feather-download');
}
if (in_array($status, ['draft', 'open'], true) && $can('receiving') && (float) array_sum(array_column($lines, 'qty_received_base')) === 0.0) {
    $actions .= '<button type="button" class="btn btn-light-brand" id="purchase-order-view-cancel-btn" hx-post="/purchase-orders/' . e($id) . '/cancel" hx-target="#page-content" hx-swap="innerHTML" hx-confirm="Cancel ' . e($order['number']) . '?"><i class="feather-x-circle me-2"></i><span>Cancel order</span></button>';
}
?>
<?= view('shared/page-header.php', ['title' => $order['number'], 'screen' => 'purchase-order-view', 'crumbs' => ['Receiving' => null, 'Purchase orders' => '/purchase-orders/', $order['number'] => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="purchase-order-view-content">
    <div class="row">
        <div class="col-xxl-4 col-xl-6">
            <div class="card stretch stretch-full" id="purchase-order-view-summary">
                <div class="card-body">
                    <div class="mb-4 d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold mb-0"><?= e($order['number']) ?></h5>
                        <?= status_badge($status, 'purchase-order-view-status') ?>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-truck"></i>Supplier</span><span id="purchase-order-view-supplier"><?= e($order['supplier_name']) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-home"></i>Premises</span><span id="purchase-order-view-premises"><?= e($order['premises_name']) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-calendar"></i>Ordered</span><span id="purchase-order-view-ordered-on"><?= e(format_date($order['ordered_on'])) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-clock"></i>Expected</span><span id="purchase-order-view-expected-on" class="<?= $order['overdue'] ? 'text-danger fw-semibold' : '' ?>"><?= e(format_date($order['expected_on'])) ?><?= $order['overdue'] ? ' (overdue)' : '' ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-user"></i>Created by</span><span id="purchase-order-view-created-by"><?= e($order['created_by_name']) ?></span></li>
                        <li class="hstack justify-content-between mb-0"><span class="text-muted fw-medium hstack gap-3"><i class="feather-check-circle"></i>Approved</span><span id="purchase-order-view-approved"><?= e($order['approved_by_name'] ? $order['approved_by_name'] . ', ' . format_date($order['approved_at']) : 'Not yet') ?></span></li>
                    </ul>
                    <?php if ($order['notes']): ?><p class="mt-4 mb-0 text-muted fs-12" id="purchase-order-view-notes"><?= e($order['notes']) ?></p><?php endif; ?>
                    <?php if (in_array($status, ['open', 'partial'], true) && $can('receiving') && $shortReasons !== []): ?>
                        <form class="mt-4 pt-4 border-top" id="purchase-order-view-close-short-form" hx-post="/purchase-orders/<?= e($id) ?>/close-short" hx-target="#page-content" hx-swap="innerHTML">
                            <label class="fw-semibold fs-12 mb-2" for="purchase-order-view-close-short-reason">Close the rest short</label>
                            <div class="input-group">
                                <select class="form-select" name="reason_code_id" id="purchase-order-view-close-short-reason" required>
                                    <?php foreach ($shortReasons as $reasonId => $reason): ?><option value="<?= e($reasonId) ?>"><?= e($reason) ?></option><?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-light-brand" id="purchase-order-view-close-short-btn" hx-confirm="Close the outstanding lines of <?= e($order['number']) ?> short?">Close short</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xxl-8 col-xl-6">
            <div class="card border-top-0" id="purchase-order-view-tabs-card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="purchase-order-view-tabs" role="tablist">
                        <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="purchase-order-view-tab-lines" class="nav-link active" data-bs-toggle="tab" data-bs-target="#purchase-order-view-pane-lines" role="tab">Lines</a></li>
                        <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="purchase-order-view-tab-receipts" class="nav-link" data-bs-toggle="tab" data-bs-target="#purchase-order-view-pane-receipts" role="tab">Receipts (<?= e(count($receipts)) ?>)</a></li>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="purchase-order-view-pane-lines" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="purchase-order-view-lines-table">
                                <thead class="thead-light"><tr><th>#</th><th>Item</th><th>Ordered</th><th>Received</th><th>Outstanding</th><th>Price</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php foreach ($lines as $line): $lid = (int) $line['id']; ?>
                                    <tr id="purchase-order-line-row-<?= e($lid) ?>">
                                        <td><?= e($line['line_no']) ?></td>
                                        <td id="purchase-order-line-row-<?= e($lid) ?>-item"><?= e($line['item_code']) ?> <small class="text-muted"><?= e($line['item_name']) ?></small></td>
                                        <td id="purchase-order-line-row-<?= e($lid) ?>-ordered"><?= e(format_qty($line['qty_ordered'], 2) . ' ' . $line['purchase_unit_code']) ?> <small class="text-muted">(<?= e(fmt_qty($line['qty_ordered_base'], $line['base_unit_code'])) ?>)</small></td>
                                        <td id="purchase-order-line-row-<?= e($lid) ?>-received"><?= fmt_qty_html($line['qty_received_base'], $line['base_unit_code']) ?></td>
                                        <td id="purchase-order-line-row-<?= e($lid) ?>-outstanding"><?= fmt_qty_html(max(0, (float) $line['qty_outstanding_base']), $line['base_unit_code']) ?></td>
                                        <td id="purchase-order-line-row-<?= e($lid) ?>-price">$<?= e(number_format((float) $line['unit_price'], 2)) ?> <small class="text-muted">/ <?= e($line['purchase_unit_code']) ?></small></td>
                                        <td id="purchase-order-line-row-<?= e($lid) ?>-status"><?= status_badge($line['status']) ?><?= $line['close_reason'] ? ' <small class="text-muted">' . e($line['close_reason']) . '</small>' : '' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($lines === []): ?><tr><td colspan="7" class="text-center text-muted py-4">No lines yet.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="purchase-order-view-pane-receipts" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="purchase-order-view-receipts-table">
                                <thead class="thead-light"><tr><th>Receipt</th><th>Received</th><th>Status</th><th>Lines</th><th>Delivery note</th></tr></thead>
                                <tbody>
                                <?php foreach ($receipts as $receipt): $rid = (int) $receipt['id']; ?>
                                    <tr id="purchase-order-receipt-row-<?= e($rid) ?>">
                                        <td><a <?= nav_attrs('/receipts/' . $rid) ?>><?= status_dot(status_color($receipt['status'])) ?><?= e($receipt['number']) ?></a></td>
                                        <td><?= e(format_date($receipt['received_at'])) ?></td>
                                        <td><?= status_badge($receipt['status']) ?></td>
                                        <td><?= e($receipt['line_count']) ?></td>
                                        <td><?= e($receipt['delivery_note_ref']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($receipts === []): ?><tr><td colspan="5" class="text-center text-muted py-4">Nothing received yet.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
