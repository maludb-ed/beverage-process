<?php /** @var array $order  @var array $vessels  @var array $conflicts  @var array $materials  @var array $allocations  @var array $user */
$id = (int) $order['id'];
$status = $order['status'];
$can = static fn(string ...$roles) => user_can($user, ...$roles);
$post = static fn(string $btnId, string $verb, string $icon, string $label, string $class, string $confirm = '') =>
    '<button type="button" class="btn ' . $class . '" id="' . e($btnId) . '" hx-post="/production-orders/' . e($id) . '/' . $verb . '" hx-target="#page-content" hx-swap="innerHTML"'
    . ($confirm !== '' ? ' hx-confirm="' . e($confirm) . '"' : '') . '><i class="' . e($icon) . ' me-2"></i><span>' . e($label) . '</span></button>';
$actions = '';
if ($status === 'planned' && $can('production')) {
    $actions .= nav_button('production-order-view-edit-btn', '/production-orders/' . $id . '/edit', 'Edit', 'feather-edit', 'btn btn-light-brand');
    $actions .= $post('production-order-view-release-btn', 'release', 'feather-send', 'Release', 'btn-primary');
}
if (in_array($status, ['complete', 'in_progress'], true) && $can('production')) {
    $actions .= $post('production-order-view-close-btn', 'close', 'feather-check-circle', 'Close', 'btn-primary');
}
if (in_array($status, ['planned', 'released'], true) && $can('production')) {
    $actions .= $post('production-order-view-cancel-btn', 'cancel', 'feather-x-circle', 'Cancel order', 'btn-light-brand', 'Cancel ' . $order['number'] . '? Its allocations will be released.');
}
$when = static fn(?string $name, ?string $at) => $name ? $name . ', ' . format_date($at) : 'Not yet';
?>
<?= view('shared/page-header.php', ['title' => $order['number'], 'screen' => 'production-order-view', 'crumbs' => ['Production' => null, 'Production orders' => '/production-orders/', $order['number'] => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="production-order-view-content">
    <div class="row">
        <div class="col-xxl-4 col-xl-6">
            <div class="card stretch stretch-full" id="production-order-view-summary">
                <div class="card-body">
                    <div class="mb-4 d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold mb-0"><?= e($order['number']) ?></h5>
                        <?= production_order_status_badge($status, 'production-order-view-status') ?>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-package"></i>Product</span><span id="production-order-view-product"><?= e($order['product_name']) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-book-open"></i>Recipe</span><span id="production-order-view-recipe"><a <?= nav_attrs('/recipes/' . (int) $order['recipe_version_id']) ?>>v<?= e($order['version_no']) ?></a></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-droplet"></i>Planned volume</span><span id="production-order-view-volume"><?= fmt_qty_html($order['planned_volume_l'], 'L') ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-calendar"></i>Pitch</span><span id="production-order-view-pitch-on"><?= e(format_date($order['planned_pitch_on'])) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-calendar"></i>Package</span><span id="production-order-view-package-on"><?= e(format_date($order['planned_package_on'])) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-home"></i>Premises</span><span id="production-order-view-premises"><?= e($order['premises_name']) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-user"></i>Created</span><span id="production-order-view-created"><?= e($when($order['created_by_name'], $order['created_at'])) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-send"></i>Released</span><span id="production-order-view-released"><?= e($when($order['released_by_name'], $order['released_at'])) ?></span></li>
                        <li class="hstack justify-content-between mb-0"><span class="text-muted fw-medium hstack gap-3"><i class="feather-check-circle"></i>Closed</span><span id="production-order-view-closed"><?= e($when($order['closed_by_name'], $order['closed_at'])) ?></span></li>
                    </ul>
                    <?php if ($order['notes']): ?><p class="mt-4 mb-0 text-muted fs-12" id="production-order-view-notes"><?= e($order['notes']) ?></p><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xxl-8 col-xl-6">
            <div class="card stretch stretch-full" id="production-order-view-vessels">
                <div class="card-header"><h5 class="card-title">Vessel plan</h5></div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="production-order-view-vessels-table">
                        <thead class="thead-light"><tr><th>Vessel</th><th>Role</th><th>From</th><th>To</th><th>Capacity</th><th>Conflicts</th></tr></thead>
                        <tbody>
                        <?php foreach ($vessels as $v): $vid = (int) $v['id']; ?>
                            <tr id="production-order-vessel-row-<?= e($vid) ?>">
                                <td id="production-order-vessel-row-<?= e($vid) ?>-vessel"><?= e($v['vessel_name']) ?></td>
                                <td id="production-order-vessel-row-<?= e($vid) ?>-role"><?= e(humanize($v['role'])) ?></td>
                                <td id="production-order-vessel-row-<?= e($vid) ?>-from"><?= e(format_date($v['planned_from'])) ?></td>
                                <td id="production-order-vessel-row-<?= e($vid) ?>-to"><?= e(format_date($v['planned_to'])) ?></td>
                                <td id="production-order-vessel-row-<?= e($vid) ?>-capacity"><?= fmt_qty_html($v['capacity_l'], 'L') ?></td>
                                <td id="production-order-vessel-row-<?= e($vid) ?>-conflict">
                                    <?php foreach ($conflicts[$vid] ?? [] as $conflict): ?><div><?= badge($conflict['label'], 'warning') ?></div><?php endforeach; ?>
                                    <?php if (empty($conflicts[$vid])): ?><span class="text-muted">None</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($vessels === []): ?><tr><td colspan="6" class="text-center text-muted py-4">No vessels planned.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-12">
            <div class="card stretch stretch-full" id="production-order-view-materials">
                <div class="card-header"><h5 class="card-title">Material check</h5></div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="production-order-view-materials-table">
                        <thead class="thead-light"><tr><th>Item</th><th>Purpose</th><th>Required</th><th>Available</th><th>Allocated to other orders</th><th>On order</th><th>Shortfall</th></tr></thead>
                        <tbody>
                        <?php foreach ($materials as $m): $mid = (int) $m['id']; $short = (float) $m['shortfall_base'] > 0; ?>
                            <tr id="production-order-material-row-<?= e($mid) ?>">
                                <td id="production-order-material-row-<?= e($mid) ?>-item"><?= status_dot($short ? 'danger' : 'success') ?><?= e($m['item_code']) ?> <small class="text-muted"><?= e($m['item_name']) ?></small></td>
                                <td id="production-order-material-row-<?= e($mid) ?>-purpose"><?= e(humanize($m['purpose'])) ?></td>
                                <td id="production-order-material-row-<?= e($mid) ?>-required"><?= fmt_qty_html($m['required_base'], $m['base_unit_code'], 2) ?> <small class="text-muted">(<?= e(format_qty($m['required_base'], 3) . ' ' . $m['base_unit_code']) ?>)</small></td>
                                <td id="production-order-material-row-<?= e($mid) ?>-available"><?= fmt_qty_html($m['available_base'], $m['base_unit_code'], 2) ?></td>
                                <td id="production-order-material-row-<?= e($mid) ?>-allocated-other"><?= (float) $m['allocated_other_base'] > 0 ? fmt_qty_html($m['allocated_other_base'], $m['base_unit_code'], 2) : '<span class="text-muted">None</span>' ?></td>
                                <td id="production-order-material-row-<?= e($mid) ?>-on-order"><?= fmt_qty_html($m['on_order_base'], $m['base_unit_code'], 2) ?></td>
                                <td id="production-order-material-row-<?= e($mid) ?>-shortfall" class="<?= $short ? 'text-danger fw-semibold' : '' ?>"><?= $short ? fmt_qty_html($m['shortfall_base'], $m['base_unit_code'], 2) : '<span class="text-muted">None</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($materials === []): ?><tr><td colspan="7" class="text-center text-muted py-4">The recipe has no material lines.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php if ($status !== 'planned'): ?>
    <div class="row">
        <div class="col-lg-12">
            <div class="card stretch stretch-full" id="production-order-view-allocations">
                <div class="card-header"><h5 class="card-title">Allocations</h5></div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="production-order-view-allocations-table">
                        <thead class="thead-light"><tr><th>Item</th><th>Lot</th><th>Quantity</th><th>Created</th><th>Released</th></tr></thead>
                        <tbody>
                        <?php foreach ($allocations as $a): $aid = (int) $a['id']; ?>
                            <tr id="production-order-allocation-row-<?= e($aid) ?>">
                                <td id="production-order-allocation-row-<?= e($aid) ?>-item"><?= e($a['item_code']) ?> <small class="text-muted"><?= e($a['item_name']) ?></small></td>
                                <td id="production-order-allocation-row-<?= e($aid) ?>-lot"><?= e($a['lot_number'] ?? 'Any lot') ?></td>
                                <td id="production-order-allocation-row-<?= e($aid) ?>-qty"><?= fmt_qty_html($a['qty_base'], $a['base_unit_code'], 2) ?></td>
                                <td id="production-order-allocation-row-<?= e($aid) ?>-created"><?= e(format_datetime($a['created_at'])) ?></td>
                                <td id="production-order-allocation-row-<?= e($aid) ?>-released"><?= e($a['released_at'] ? format_datetime($a['released_at']) : 'Open') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($allocations === []): ?><tr><td colspan="5" class="text-center text-muted py-4">No allocations.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
