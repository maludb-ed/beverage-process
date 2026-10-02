<?php /** @var array $customer  @var array $removals  @var array $kegs  @var array $user  @var array $orders  @var array $history */
$id = (int) $customer['id'];
$canEdit = user_can($user, 'compliance');
$out = array_values(array_filter($removals, static fn($r) => $r['direction'] === 'out'));
$returns = array_values(array_filter($removals, static fn($r) => $r['direction'] === 'in'));
$actions = '';
if ($canEdit) {
    $actions .= nav_button('customer-view-edit-btn', '/customers/' . $id . '/edit', 'Edit', 'feather-edit', 'btn btn-light-brand');
    $actions .= nav_button('customer-view-removal-btn', '/removals/new?customer=' . rawurlencode($customer['name']), 'Add removal', 'feather-log-out');
    $actions .= nav_button('customer-view-return-btn', '/removals/new?direction=in&customer=' . rawurlencode($customer['name']), 'Add return', 'feather-log-in', 'btn btn-light-brand');
}
// Delete with no history; with history, deactivate (the same endpoint decides). Sales may do this too.
if (user_can($user, 'compliance', 'sales') && ($history === [] || $customer['active'])) {
    $actions .= $history === []
        ? '<button type="button" class="btn btn-light-brand" id="customer-view-delete-btn" hx-post="/customers/' . e($id) . '/delete" hx-target="#page-content" hx-swap="innerHTML" hx-confirm="Delete ' . e($customer['name']) . '? It has no orders, removals or kegs, so it is removed for good."><i class="feather-trash-2 me-2"></i><span>Delete</span></button>'
        : '<button type="button" class="btn btn-light-brand" id="customer-view-delete-btn" hx-post="/customers/' . e($id) . '/delete" hx-target="#page-content" hx-swap="innerHTML" hx-confirm="' . e($customer['name']) . ' has history (' . e(history_summary($history)) . '), so it cannot be deleted. Deactivate it instead?"><i class="feather-slash me-2"></i><span>Deactivate</span></button>';
}
$removalTable = static function (array $rows, string $key, string $empty): string {
    ob_start(); ?>
    <div class="table-responsive">
        <table class="table table-hover mb-0" id="customer-view-<?= e($key) ?>-table">
            <thead class="thead-light"><tr><th>Number</th><th>Date</th><th>Destination</th><th>Units</th><th>Gallons</th><th>Tax</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $rid = (int) $r['id']; ?>
                <tr id="customer-<?= e($key) ?>-row-<?= e($rid) ?>">
                    <td><a <?= nav_attrs('/removals/' . $rid) ?>><?= status_dot(status_color($r['status'])) ?><?= e($r['number']) ?></a></td>
                    <td><?= e(format_date($r['removed_at'])) ?></td>
                    <td><?= e(humanize($r['destination_kind'])) ?><?= $r['reference'] ? ' <small class="text-muted">' . e($r['reference']) . '</small>' : '' ?></td>
                    <td><?= e($r['units']) ?></td>
                    <td><?= $r['wine_gallons'] !== null ? e(number_format((float) $r['wine_gallons'], 2)) . ' gal' : '' ?></td>
                    <td><?= $r['tax_determined'] && $r['tax_amount'] !== null ? '$' . e(number_format((float) $r['tax_amount'], 2)) : '' ?></td>
                    <td><?= status_badge($r['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?><tr id="customer-view-<?= e($key) ?>-empty"><td colspan="7" class="text-center text-muted py-4"><?= e($empty) ?></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php return (string) ob_get_clean();
};
?>
<?= view('shared/page-header.php', ['title' => $customer['name'], 'screen' => 'customer-view', 'crumbs' => ['Compliance' => null, 'Customers' => '/customers/', $customer['name'] => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="customer-view-content">
    <div class="row">
        <div class="col-xxl-4 col-xl-6">
            <div class="card" id="customer-view-summary">
                <div class="card-body">
                    <div class="mb-4 d-flex align-items-center justify-content-between">
                        <div><h5 class="fw-bold mb-1"><?= e($customer['name']) ?></h5><div class="fs-12 text-muted"><?= e(CUSTOMER_KINDS[$customer['kind']] ?? humanize($customer['kind'])) ?></div></div>
                        <?= status_badge($customer['active'] ? 'active' : 'inactive', 'customer-view-status') ?>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-log-out"></i>Default destination</span><span id="customer-view-default-destination"><?= e(CUSTOMER_DESTINATIONS[$customer['default_destination']] ?? humanize($customer['default_destination'])) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-hash"></i>Permit</span><span id="customer-view-permit-number"><?= e($customer['permit_number'] ?: '—') ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-user"></i>Contact</span><span id="customer-view-contact-name"><?= e($customer['contact_name'] ?: '—') ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-mail"></i>Email</span><span id="customer-view-email" class="text-break"><?= $customer['email'] ? '<a href="mailto:' . e($customer['email']) . '">' . e($customer['email']) . '</a>' : '—' ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-phone"></i>Phone</span><span id="customer-view-phone"><?= e($customer['phone'] ?: '—') ?></span></li>
                        <li class="hstack justify-content-between mb-0"><span class="text-muted fw-medium hstack gap-3"><i class="feather-disc"></i>Kegs out</span><span id="customer-view-kegs-out" class="fw-semibold"><?= e($customer['kegs_out']) ?></span></li>
                    </ul>
                    <?php if ($customer['address']): ?><p class="mt-4 mb-0 fs-12" id="customer-view-address"><?= nl2br(e($customer['address'])) ?></p><?php endif; ?>
                    <?php if ($customer['notes']): ?><p class="mt-4 mb-0 text-muted fs-12" id="customer-view-notes"><?= e($customer['notes']) ?></p><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xxl-8 col-xl-6">
            <div class="card border-top-0" id="customer-view-tabs-card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="customer-view-tabs" role="tablist">
                        <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="customer-view-tab-removals" class="nav-link active" data-bs-toggle="tab" data-bs-target="#customer-view-pane-removals" role="tab">Removals (<?= e(count($out)) ?>)</a></li>
                        <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="customer-view-tab-kegs" class="nav-link" data-bs-toggle="tab" data-bs-target="#customer-view-pane-kegs" role="tab">Kegs out (<?= e(count($kegs)) ?>)</a></li>
                        <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="customer-view-tab-orders" class="nav-link" data-bs-toggle="tab" data-bs-target="#customer-view-pane-orders" role="tab">Orders (<?= e($orders['total']) ?>)</a></li>
                        <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="customer-view-tab-returns" class="nav-link" data-bs-toggle="tab" data-bs-target="#customer-view-pane-returns" role="tab">Returns (<?= e(count($returns)) ?>)</a></li>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="customer-view-pane-removals" role="tabpanel"><?= $removalTable($out, 'removals', 'Nothing has been removed to this customer yet.') ?></div>
                    <div class="tab-pane fade" id="customer-view-pane-kegs" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="customer-view-kegs-table">
                                <thead class="thead-light"><tr><th>Serial</th><th>Size</th><th>Lot</th><th>Shipped</th><th>Days out</th></tr></thead>
                                <tbody>
                                <?php foreach ($kegs as $keg): $kid = (int) $keg['id']; ?>
                                    <tr id="customer-keg-row-<?= e($kid) ?>">
                                        <td><a <?= nav_attrs('/kegs/' . $kid) ?>><?= status_dot(status_color($keg['state'])) ?><?= e($keg['serial']) ?></a></td>
                                        <td><?= fmt_qty_html($keg['size_l'], 'L', 2) ?></td>
                                        <td><?php if ($keg['current_lot_id']): ?><a <?= nav_attrs('/finished-lots/' . (int) $keg['current_lot_id']) ?>><?= e($keg['lot_number']) ?></a><?php endif; ?></td>
                                        <td><?= e(format_date($keg['last_moved_at'])) ?></td>
                                        <td><?= e($keg['days_out'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($kegs === []): ?><tr id="customer-view-kegs-empty"><td colspan="5" class="text-center text-muted py-4">No kegs at this customer.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="customer-view-pane-orders" role="tabpanel"><?= view('orders/partials/embedded-table.php', [
                        'prefix' => 'customer-view', 'result' => $orders, 'canPrice' => user_can($user, 'sales'), 'empty' => 'No orders from this customer yet.',
                        'newUrl' => user_can($user, 'sales') && $customer['active'] ? '/orders/new?customer=' . rawurlencode($customer['name']) : null,
                    ]) ?></div>
                    <div class="tab-pane fade" id="customer-view-pane-returns" role="tabpanel"><?= $removalTable($returns, 'returns', 'No returns from this customer.') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>
