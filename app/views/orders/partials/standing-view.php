<?php /** @var array $standing  @var array $lines  @var array $occurrences  @var array $orders  @var array $user  @var bool $canPrice */
$id = (int) $standing['id'];
$s = 'standing-order-view';
$canEdit = user_can($user, 'sales');
$running = $standing['active'] && ($standing['ends_on'] === null || $standing['ends_on'] >= today());
$actions = '';
if ($canEdit) {
    $actions .= nav_button($s . '-edit-btn', '/orders/standing/' . $id . '/edit', 'Edit', 'feather-edit', 'btn btn-light-brand');
    $actions .= '<button type="button" class="btn btn-light-brand" id="' . $s . '-active-btn" hx-post="/orders/standing/' . $id . '/active" hx-vals=\'{"active": "' . ($standing['active'] ? '0' : '1') . '"}\' hx-target="#page-content" hx-swap="innerHTML"'
        . ($standing['active'] ? ' hx-confirm="Pause ' . e($standing['number']) . '? Its dates stop counting as demand."' : '') . '><i class="feather-' . ($standing['active'] ? 'pause' : 'play') . ' me-2"></i><span>' . ($standing['active'] ? 'Pause' : 'Resume') . '</span></button>';
}
$total = array_sum(array_map(static fn($l) => (float) $l['line_total'], $lines));
?>
<?= view('shared/page-header.php', ['title' => $standing['number'], 'screen' => $s, 'crumbs' => ['Sales' => null, 'Standing orders' => '/orders/standing', $standing['number'] => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="<?= $s ?>-content">
    <div class="row">
        <div class="col-xxl-4 col-xl-6">
            <div class="card" id="<?= $s ?>-summary">
                <div class="card-body">
                    <div class="mb-4 d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold mb-0"><?= e($standing['number']) ?></h5>
                        <?= badge($running ? 'Running' : ($standing['active'] ? 'Ended' : 'Paused'), $running ? 'success' : 'secondary', $s . '-state') ?>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <li class="hstack justify-content-between gap-3 mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-users"></i>Customer</span><span id="<?= $s ?>-customer"><a <?= nav_attrs('/customers/' . (int) $standing['customer_id']) ?>><?= e($standing['customer_name']) ?></a></span></li>
                        <li class="hstack justify-content-between gap-3 mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-repeat"></i>Schedule</span><span id="<?= $s ?>-schedule" class="text-end"><?= e(standing_schedule_label($standing)) ?></span></li>
                        <li class="hstack justify-content-between gap-3 mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-calendar"></i>Runs</span><span id="<?= $s ?>-dates" class="text-end"><?= e(format_date($standing['starts_on']) . ' to ' . ($standing['ends_on'] ? format_date($standing['ends_on']) : 'open-ended')) ?></span></li>
                        <?php if ($canPrice): ?><li class="hstack justify-content-between gap-3 mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-dollar-sign"></i>Value each delivery</span><span id="<?= $s ?>-value">$<?= e(number_format($total, 2)) ?></span></li><?php endif; ?>
                        <li class="hstack justify-content-between gap-3 mb-0"><span class="text-muted fw-medium hstack gap-3"><i class="feather-user"></i>Created by</span><span id="<?= $s ?>-created-by"><?= e($standing['created_by_name'] ?? '—') ?></span></li>
                    </ul>
                    <?php if ($standing['notes']): ?><p class="mt-4 mb-0 text-muted fs-12" id="<?= $s ?>-notes"><?= e($standing['notes']) ?></p><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xxl-8 col-xl-6">
            <div class="card" id="<?= $s ?>-lines-card">
                <div class="card-header"><h5 class="card-title">Each delivery</h5></div>
                <div class="table-responsive">
                    <table class="table mb-0" id="<?= $s ?>-lines-table">
                        <thead class="thead-light"><tr><th>Product and format</th><th>Units</th><?php if ($canPrice): ?><th class="text-end">Price</th><th class="text-end">Total</th><?php endif; ?></tr></thead>
                        <tbody>
                        <?php foreach ($lines as $line): ?>
                            <tr id="<?= $s ?>-line-<?= e($line['id']) ?>">
                                <td><?= e($line['configuration_name']) ?> <small class="text-muted"><?= e($line['product_name']) ?></small></td>
                                <td><?= e(number_format((int) $line['units_ordered'])) ?></td>
                                <?php if ($canPrice): ?>
                                    <td class="text-end"><?= $line['unit_price'] !== null ? '$' . e(number_format((float) $line['unit_price'], 2)) : '<span class="text-muted">list</span>' ?></td>
                                    <td class="text-end"><?= $line['line_total'] !== null ? '$' . e(number_format((float) $line['line_total'], 2)) : '—' ?></td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card" id="<?= $s ?>-dates-card">
                <div class="card-header"><h5 class="card-title">Next dates</h5></div>
                <div class="table-responsive">
                    <table class="table mb-0" id="<?= $s ?>-dates-table">
                        <thead class="thead-light"><tr><th>Date</th><th>Order</th><th class="text-end">Action</th></tr></thead>
                        <tbody>
                        <?php foreach ($occurrences as $i => $o): ?>
                            <tr id="<?= $s ?>-date-<?= e($o['occurs_on']) ?>">
                                <td><?= e(format_date($o['occurs_on'])) ?></td>
                                <td><?php if ($o['order_id']): ?><a <?= nav_attrs('/orders/' . (int) $o['order_id']) ?>><?= status_dot(order_status_color($o['order_status'])) ?><?= e($o['order_number']) ?></a><?php else: ?><span class="text-muted">Standing demand</span><?php endif; ?></td>
                                <td class="text-end">
                                    <?php if (!$o['order_id'] && $canEdit && $running): ?>
                                        <button type="button" class="btn btn-sm btn-light-brand" id="<?= $s ?>-date-<?= e($o['occurs_on']) ?>-order-btn" hx-post="/orders/standing/<?= e($id) ?>/occurrence" hx-vals='{"occurs_on": "<?= e($o['occurs_on']) ?>"}' hx-target="#page-content" hx-swap="innerHTML"><i class="feather-plus me-1"></i>Create order</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($occurrences === []): ?><tr><td colspan="3" class="text-center text-muted py-4"><?= $running ? 'No dates in the next year.' : 'Paused or ended: no upcoming dates count as demand.' ?></td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($orders !== []): ?>
            <div class="card" id="<?= $s ?>-orders-card">
                <div class="card-header"><h5 class="card-title">Orders made from it</h5></div>
                <div class="table-responsive"><table class="table mb-0" id="<?= $s ?>-orders-table"><thead class="thead-light"><tr><th>Order</th><th>Date</th><th>Status</th></tr></thead><tbody>
                    <?php foreach ($orders as $o): ?><tr id="<?= $s ?>-order-<?= e($o['id']) ?>"><td><a <?= nav_attrs('/orders/' . (int) $o['id']) ?>><?= e($o['number']) ?></a></td><td><?= e(format_date($o['standing_occurrence_on'])) ?></td><td><?= order_status_badge($o['status']) ?></td></tr><?php endforeach; ?>
                </tbody></table></div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
