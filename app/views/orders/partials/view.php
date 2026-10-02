<?php /** @var array $order  @var array $lines  @var array $runs  @var array $removals  @var array $user  @var bool $canPrice */
$id = (int) $order['id'];
$status = $order['status'];
$canEdit = user_can($user, 'sales');
$money = static fn($value) => $value === null ? '—' : '$' . number_format((float) $value, 2);
$shippedAny = (int) $order['units_shipped'] > 0 || array_sum(array_map('intval', array_column($lines, 'units_in_packaging_runs'))) > 0;
$actions = '';
if (in_array($status, ['draft', 'confirmed'], true) && $canEdit) {
    $actions .= nav_button('order-view-edit-btn', '/orders/' . $id . '/edit', 'Edit', 'feather-edit', 'btn btn-light-brand');
}
if ($status === 'draft' && $canEdit) {
    $actions .= '<button type="button" class="btn btn-primary" id="order-view-confirm-btn" hx-post="/orders/' . e($id) . '/confirm" hx-target="#page-content" hx-swap="innerHTML"><i class="feather-check-circle me-2"></i><span>Confirm</span></button>';
}
if (in_array($status, ['confirmed', 'in_fulfillment', 'shipped'], true) && $canEdit) {
    $actions .= '<button type="button" class="btn btn-light-brand" id="order-view-close-btn" hx-post="/orders/' . e($id) . '/close" hx-target="#page-content" hx-swap="innerHTML" hx-confirm="Close ' . e($order['number']) . '? Lines not fully shipped are closed short."><i class="feather-lock me-2"></i><span>Close</span></button>';
}
$summary = [
    ['feather-users', 'Customer', '<a ' . nav_attrs('/customers/' . (int) $order['customer_id']) . '>' . e($order['customer_name']) . '</a>', 'customer'],
    ['feather-send', 'Destination', e(ORDER_DESTINATIONS[$order['destination_kind']] ?? humanize($order['destination_kind'])), 'destination'],
    ['feather-calendar', 'Ordered', e(format_date($order['ordered_on'])), 'ordered-on'],
    ['feather-clock', 'Due', '<span class="' . ($order['overdue'] ? 'text-danger fw-semibold' : '') . '">' . e(format_date($order['requested_on'])) . ($order['overdue'] ? ' (overdue)' : '') . '</span>', 'requested-on'],
    ['feather-hash', 'Customer reference', e($order['customer_reference'] ?: '—'), 'reference'],
    ['feather-git-branch', 'Origin', e(ORDER_ORIGINS[$order['origin']] ?? humanize($order['origin']))
        . ($order['standing_order_number'] ? ' ' . e($order['standing_order_number']) : '') . ($order['import_number'] ? ' ' . e($order['import_number']) : ''), 'origin'],
    ['feather-box', 'Units', e(number_format((int) $order['units_ordered'])) . ' ordered, ' . e(number_format((int) $order['units_shipped'])) . ' shipped', 'units'],
];
if ($canPrice) {
    $summary[] = ['feather-dollar-sign', 'Order value', e($money($order['order_value'])), 'value'];
}
$summary[] = ['feather-user', 'Created by', e($order['created_by_name'] ?? '—'), 'created-by'];
$summary[] = ['feather-check-circle', 'Confirmed', e($order['confirmed_by_name'] ? $order['confirmed_by_name'] . ', ' . format_date($order['confirmed_at']) : ($order['fulfilled_outside'] ? 'History' : 'Not yet')), 'confirmed'];
if ($order['closed_at']) {
    $summary[] = ['feather-lock', 'Closed', e(($order['closed_by_name'] ?? '') . ', ' . format_date($order['closed_at'])), 'closed'];
}
if ($order['cancelled_at']) {
    $summary[] = ['feather-x-circle', 'Cancelled', e(($order['cancelled_by_name'] ?? '') . ', ' . format_date($order['cancelled_at'])), 'cancelled'];
}
?>
<?= view('shared/page-header.php', ['title' => $order['number'], 'screen' => 'order-view', 'crumbs' => ['Sales' => null, 'Customer orders' => '/orders/', $order['number'] => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="order-view-content">
    <div class="row">
        <div class="col-xxl-4 col-xl-6">
            <div class="card" id="order-view-summary">
                <div class="card-body">
                    <div class="mb-4 d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold mb-0"><?= e($order['number']) ?></h5>
                        <?= order_status_badge($status, 'order-view-status') ?>
                    </div>
                    <?php if ($order['fulfilled_outside']): ?><p class="fs-12 text-muted mb-4" id="order-view-history-note">Fulfilled outside the system: kept as history for reports and forecasts.</p><?php endif; ?>
                    <ul class="list-unstyled mb-0">
                        <?php foreach ($summary as $i => [$icon, $label, $html, $key]): ?>
                            <li class="hstack justify-content-between gap-3 <?= $i === count($summary) - 1 ? 'mb-0' : 'mb-4' ?>"><span class="text-muted fw-medium hstack gap-3"><i class="<?= e($icon) ?>"></i><?= e($label) ?></span><span id="order-view-<?= e($key) ?>" class="text-end"><?= $html ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($order['cancel_reason']): ?><p class="mt-4 mb-0 text-muted fs-12" id="order-view-cancel-reason">Cancelled: <?= e($order['cancel_reason']) ?></p><?php endif; ?>
                    <?php if ($order['notes']): ?><p class="mt-4 mb-0 text-muted fs-12" id="order-view-notes"><?= e($order['notes']) ?></p><?php endif; ?>
                    <?php if (in_array($status, ['draft', 'confirmed'], true) && $canEdit && !$shippedAny): ?>
                        <form class="mt-4 pt-4 border-top" id="order-view-cancel-form" hx-post="/orders/<?= e($id) ?>/cancel" hx-target="#page-content" hx-swap="innerHTML">
                            <label class="fw-semibold fs-12 mb-2" for="order-view-cancel-reason-input">Cancel this order</label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="cancel_reason" id="order-view-cancel-reason-input" maxlength="500" placeholder="Reason" required />
                                <button type="submit" class="btn btn-light-brand" id="order-view-cancel-btn" hx-confirm="Cancel <?= e($order['number']) ?>?">Cancel order</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xxl-8 col-xl-6">
            <div class="card border-top-0" id="order-view-tabs-card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="order-view-tabs" role="tablist">
                        <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="order-view-tab-lines" class="nav-link active" data-bs-toggle="tab" data-bs-target="#order-view-pane-lines" role="tab">Lines</a></li>
                        <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="order-view-tab-packaging" class="nav-link" data-bs-toggle="tab" data-bs-target="#order-view-pane-packaging" role="tab">Packaging runs (<?= e(count($runs)) ?>)</a></li>
                        <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="order-view-tab-shipments" class="nav-link" data-bs-toggle="tab" data-bs-target="#order-view-pane-shipments" role="tab">Shipments (<?= e(count($removals)) ?>)</a></li>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="order-view-pane-lines" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="order-view-lines-table">
                                <thead class="thead-light"><tr><th>#</th><th>Product and format</th><th>Ordered</th><th>In runs</th><th>Shipped</th><th>Open</th><?php if ($canPrice): ?><th class="text-end">Price</th><th class="text-end">Total</th><?php endif; ?><th>Status</th></tr></thead>
                                <tbody>
                                <?php foreach ($lines as $line): $lid = (int) $line['id']; ?>
                                    <tr id="order-line-row-<?= e($lid) ?>">
                                        <td><?= e($line['line_no']) ?></td>
                                        <td id="order-line-row-<?= e($lid) ?>-format"><?= e($line['configuration_name']) ?> <small class="text-muted"><?= e($line['product_name']) ?></small><?= $line['notes'] ? '<div class="fs-11 text-muted">' . e($line['notes']) . '</div>' : '' ?></td>
                                        <td id="order-line-row-<?= e($lid) ?>-ordered"><?= e(number_format((int) $line['units_ordered'])) ?><?= $line['units_per_case'] ? ' <small class="text-muted">(' . e(round((int) $line['units_ordered'] / (int) $line['units_per_case'], 2)) . ' cases)</small>' : '' ?></td>
                                        <td id="order-line-row-<?= e($lid) ?>-in-runs"><?= e(number_format((int) $line['units_in_packaging_runs'])) ?></td>
                                        <td id="order-line-row-<?= e($lid) ?>-shipped"><?= e(number_format((int) $line['units_shipped'])) ?></td>
                                        <td id="order-line-row-<?= e($lid) ?>-open"><?= e(number_format((int) $line['units_open'])) ?></td>
                                        <?php if ($canPrice): ?>
                                            <td id="order-line-row-<?= e($lid) ?>-price" class="text-end"><?= e($money($line['unit_price'])) ?></td>
                                            <td id="order-line-row-<?= e($lid) ?>-total" class="text-end"><?= e($money($line['line_total'])) ?></td>
                                        <?php endif; ?>
                                        <td id="order-line-row-<?= e($lid) ?>-status"><?= badge(humanize($line['line_status']), order_status_color($line['line_status'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($lines === []): ?><tr><td colspan="<?= $canPrice ? 9 : 7 ?>" class="text-center text-muted py-4">No lines yet.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="order-view-pane-packaging" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="order-view-runs-table">
                                <thead class="thead-light"><tr><th>Run</th><th>Date</th><th>Format</th><th>Batch</th><th>Units for this order</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php foreach ($runs as $run): $rid = (int) $run['id']; ?>
                                    <tr id="order-run-row-<?= e($rid) ?>">
                                        <td><a <?= nav_attrs('/packaging-runs/' . $rid) ?>><?= status_dot(status_color($run['status'])) ?><?= e($run['number']) ?></a></td>
                                        <td><?= e(format_date($run['run_on'])) ?></td>
                                        <td><?= e($run['configuration_name']) ?></td>
                                        <td><?= e($run['batch_number']) ?></td>
                                        <td><?= e(number_format((int) $run['units_for_order'])) ?></td>
                                        <td><?= status_badge($run['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($runs === []): ?><tr><td colspan="6" class="text-center text-muted py-4">No packaging runs for this order yet.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="order-view-pane-shipments" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="order-view-shipments-table">
                                <thead class="thead-light"><tr><th>Removal</th><th>Date</th><th>Direction</th><th>Units</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php foreach ($removals as $removal): $mid = (int) $removal['id']; ?>
                                    <tr id="order-shipment-row-<?= e($mid) ?>">
                                        <td><a <?= nav_attrs('/removals/' . $mid) ?>><?= status_dot(status_color($removal['status'])) ?><?= e($removal['number']) ?></a></td>
                                        <td><?= e(format_date($removal['removed_at'])) ?></td>
                                        <td><?= e($removal['direction'] === 'out' ? 'Shipment' : 'Return') ?></td>
                                        <td><?= e(number_format((int) $removal['units'])) ?></td>
                                        <td><?= status_badge($removal['status']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($removals === []): ?><tr><td colspan="5" class="text-center text-muted py-4">Nothing shipped for this order yet.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
