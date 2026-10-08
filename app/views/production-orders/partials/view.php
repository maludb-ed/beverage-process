<?php /** @var array $order  @var array $plan  @var array $materials  @var array $allocations  @var array $consumed  @var array $processing  @var array $outputs  @var array $user */
$id = (int) $order['id'];
$status = $order['status'];
$can = static fn(string ...$roles) => user_can($user, ...$roles);
$post = static fn(string $btnId, string $verb, string $icon, string $label, string $class, string $confirm = '') =>
    '<button type="button" class="btn ' . $class . '" id="' . e($btnId) . '" hx-post="/production-orders/' . e($id) . '/' . $verb . '" hx-target="#page-content" hx-swap="innerHTML"'
    . ($confirm !== '' ? ' hx-confirm="' . e($confirm) . '"' : '') . '><i class="' . e($icon) . ' me-2"></i><span>' . e($label) . '</span></button>';
$scheduleUrl = '/schedule/' . ($plan !== [] ? '?resource=' . $plan[0]['resource_kind'] . ':' . (int) $plan[0]['resource_id'] . '&from=' . $plan[0]['local_from'] : '');
$actions = nav_button('production-order-view-schedule-btn', $scheduleUrl, 'Schedule', 'feather-calendar', 'btn btn-light-brand');
if (in_array($status, ['planned', 'released', 'in_progress'], true) && $can('production')) {
    $actions .= nav_button('production-order-view-reserve-btn', '/reservations/new?subject_kind=production_order&subject_id=' . $id, 'Reserve', 'feather-bookmark', 'btn btn-light-brand');
}
if ($status === 'planned' && $can('production')) {
    $actions .= nav_button('production-order-view-edit-btn', '/production-orders/' . $id . '/edit', 'Edit', 'feather-edit', 'btn btn-light-brand');
    $actions .= $post('production-order-view-release-btn', 'release', 'feather-send', 'Release', 'btn-primary');
}
if (in_array($status, ['complete', 'in_progress'], true) && $can('production')) {
    $actions .= $post('production-order-view-close-btn', 'close', 'feather-check-circle', 'Close', 'btn-primary');
}
if (in_array($status, ['planned', 'released'], true) && $can('production')) {
    $actions .= $post('production-order-view-cancel-btn', 'cancel', 'feather-x-circle', 'Cancel order', 'btn-light-brand', 'Cancel ' . $order['number'] . '? Its allocations and equipment bookings will be released.');
}
$when = static fn(?string $name, ?string $at) => $name ? $name . ', ' . format_date($at) : 'Not yet';
$dayOrDash = static fn(?string $d) => $d === null ? '<span class="text-muted">—</span>' : e(format_date($d));
?>
<?= view('shared/page-header.php', ['title' => $order['number'], 'screen' => 'production-order-view', 'crumbs' => ['Production' => null, 'Production orders' => '/production-orders/', $order['number'] => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="production-order-view-content">
    <div class="row">
        <div class="col-xxl-4 col-xl-5">
            <div class="card" id="production-order-view-summary">
                <div class="card-body">
                    <div class="mb-4 d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold mb-0"><?= e($order['number']) ?></h5>
                        <?= production_order_status_badge($status, 'production-order-view-status') ?>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-package"></i>Product</span><span id="production-order-view-product"><a <?= nav_attrs('/products/' . (int) $order['product_id']) ?>><?= e($order['product_name']) ?></a></span></li>
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
        <div class="col-xxl-8 col-xl-7">
            <div class="card" id="production-order-view-plan">
                <div class="card-header"><h5 class="card-title">Equipment plan</h5></div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="production-order-view-plan-table">
                        <thead class="thead-light"><tr><th>Resource</th><th>Role</th><th>When</th><th>Days</th><th>Capacity</th><th>Clashes</th></tr></thead>
                        <tbody>
                        <?php foreach ($plan as $b): $bid = (int) $b['id']; $rowId = 'production-order-plan-row-' . $bid;
                            $resourceUrl = $b['resource_kind'] === 'vessel' ? '/vessels/' . (int) $b['resource_id'] . '/edit' : '/equipment/' . (int) $b['resource_id'];
                            $tooSmall = $b['capacity_l'] !== null && (float) $b['capacity_l'] < (float) $order['planned_volume_l']; ?>
                            <tr id="<?= e($rowId) ?>">
                                <td id="<?= e($rowId) ?>-resource"><a <?= nav_attrs('/reservations/' . $bid) ?>><?= e($b['resource_name']) ?></a> <small class="text-muted"><?= e(humanize($b['resource_type'])) ?></small><?= $b['resource_status'] === 'out_of_service' ? ' ' . badge('Out of service', 'danger') : '' ?></td>
                                <td id="<?= e($rowId) ?>-role"><?= e(humanize($b['role'])) ?></td>
                                <td id="<?= e($rowId) ?>-when"><?= e(reservation_window_label($b)) ?></td>
                                <td id="<?= e($rowId) ?>-days"><?= e(reservation_days($b)) ?></td>
                                <td id="<?= e($rowId) ?>-capacity" class="<?= $tooSmall ? 'text-warning' : '' ?>"><?= $b['capacity_l'] === null ? '<span class="text-muted">—</span>' : fmt_qty_html($b['capacity_l'], 'L') ?></td>
                                <td id="<?= e($rowId) ?>-clashes">
                                    <?php foreach ($b['clashes'] as $c): ?><div><?= badge(($b['shared'] ? 'Shared with ' : 'Overlaps ') . ($c['subject_number'] ?? humanize($c['kind'])), 'warning') ?></div><?php endforeach; ?>
                                    <?php if ($b['occupant'] !== null): ?><div><?= badge('Occupied now by ' . $b['occupant']['label'], 'warning') ?></div><?php endif; ?>
                                    <?php if ($b['clashes'] === [] && $b['occupant'] === null): ?><span class="text-muted">None</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($plan === []): ?><tr id="production-order-plan-empty"><td colspan="6" class="text-center text-muted py-4">Nothing booked for this order.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-12">
            <div class="card" id="production-order-view-materials">
                <div class="card-header"><h5 class="card-title">Inputs</h5></div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="production-order-view-materials-table">
                        <thead class="thead-light"><tr><th>Item</th><th>Purpose</th><th>Required</th><th>Pick from (oldest first)</th><th>Available</th><th>Allocated to other orders</th><th>On order</th><th>Shortfall</th><th>Consumed so far</th></tr></thead>
                        <tbody>
                        <?php foreach ($materials as $m): $mid = (int) $m['id']; $short = (float) $m['shortfall_base'] > 0; $used = $consumed[(int) $m['item_id']] ?? null; ?>
                            <tr id="production-order-material-row-<?= e($mid) ?>">
                                <td id="production-order-material-row-<?= e($mid) ?>-item"><?= status_dot($short ? 'danger' : 'success') ?><a <?= nav_attrs('/items/' . (int) $m['item_id']) ?>><?= e($m['item_code']) ?></a> <small class="text-muted"><?= e($m['item_name']) ?></small></td>
                                <td id="production-order-material-row-<?= e($mid) ?>-purpose"><?= e(humanize($m['purpose'])) ?></td>
                                <td id="production-order-material-row-<?= e($mid) ?>-required"><?= fmt_qty_html($m['required_base'], $m['base_unit_code'], 2) ?> <small class="text-muted">(<?= e(format_qty($m['required_base'], 3) . ' ' . $m['base_unit_code']) ?>)</small></td>
                                <td id="production-order-material-row-<?= e($mid) ?>-pick">
                                    <?php foreach ($m['picks'] as $k => $pick): $pid = 'production-order-material-row-' . $mid . '-pick-' . $k; ?>
                                        <div class="text-nowrap<?= $k > 0 ? ' mt-1' : '' ?>" id="<?= e($pid) ?>">
                                            <span class="fw-semibold" id="<?= e($pid) ?>-where"><?= e($pick['where']) ?></span>
                                            <small class="text-muted">· <a id="<?= e($pid) ?>-lot" <?= nav_attrs('/lots/' . $pick['lot_id']) ?>><?= e($pick['lot_number']) ?></a> · <?= e(fmt_qty($pick['qty_base'], $m['base_unit_code'], 2)) ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if ($m['picks'] !== [] && (float) $m['required_base'] - $m['pick_short_base'] > (float) $m['available_base'] + 1e-9): ?><div class="text-warning fs-12 mt-1" id="production-order-material-row-<?= e($mid) ?>-pick-reserved">Other orders have reserved some of this stock</div><?php endif; ?>
                                    <?php if ($m['pick_short_base'] > 0): ?><div class="text-danger fs-12<?= $m['picks'] !== [] ? ' mt-1' : '' ?>" id="production-order-material-row-<?= e($mid) ?>-pick-short"><?= $m['picks'] === [] ? 'No released stock' : 'Still need ' . e(fmt_qty($m['pick_short_base'], $m['base_unit_code'], 2)) ?></div><?php endif; ?>
                                </td>
                                <td id="production-order-material-row-<?= e($mid) ?>-available"><?= fmt_qty_html($m['available_base'], $m['base_unit_code'], 2) ?></td>
                                <td id="production-order-material-row-<?= e($mid) ?>-allocated-other"><?= (float) $m['allocated_other_base'] > 0 ? fmt_qty_html($m['allocated_other_base'], $m['base_unit_code'], 2) : '<span class="text-muted">None</span>' ?></td>
                                <td id="production-order-material-row-<?= e($mid) ?>-on-order"><?= fmt_qty_html($m['on_order_base'], $m['base_unit_code'], 2) ?></td>
                                <td id="production-order-material-row-<?= e($mid) ?>-shortfall" class="<?= $short ? 'text-danger fw-semibold' : '' ?>"><?= $short ? fmt_qty_html($m['shortfall_base'], $m['base_unit_code'], 2) : '<span class="text-muted">None</span>' ?></td>
                                <td id="production-order-material-row-<?= e($mid) ?>-consumed"><?= $used === null ? '<span class="text-muted">—</span>' : fmt_qty_html($used, $m['base_unit_code'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($materials === []): ?><tr><td colspan="9" class="text-center text-muted py-4">The recipe has no material lines.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($consumed !== []): ?><div class="card-footer fs-12 text-muted" id="production-order-view-materials-footer">Consumed so far is what the order's batches have drawn, by item; the batch pages list the lots.</div><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-12">
            <div class="card" id="production-order-view-processing">
                <div class="card-header"><h5 class="card-title">Processing time</h5></div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="production-order-view-processing-table">
                        <thead class="thead-light"><tr><th>Stage</th><th>Expected days</th><th>Planned start</th><th>Planned end</th><th>Actual start</th><th>Actual end</th><th>Actual days</th><th>Variance</th><th>Booked</th></tr></thead>
                        <tbody>
                        <?php foreach ($processing['stages'] as $st): $sid = 'production-order-stage-row-' . (int) $st['seq']; ?>
                            <tr id="<?= e($sid) ?>">
                                <td id="<?= e($sid) ?>-stage"><span class="fw-semibold"><?= e($st['stage_name']) ?></span><?php if ($st['instructions']): ?> <small class="text-muted"><?= e($st['instructions']) ?></small><?php endif; ?></td>
                                <td id="<?= e($sid) ?>-expected"><?= $st['expected_duration_days'] === null ? '<span class="text-muted">—</span>' : e($st['expected_duration_days']) ?></td>
                                <td id="<?= e($sid) ?>-planned-start"><?= $dayOrDash($st['planned_start']) ?></td>
                                <td id="<?= e($sid) ?>-planned-end"><?= $dayOrDash($st['planned_end']) ?></td>
                                <td id="<?= e($sid) ?>-actual-start"><?= $st['entered_at'] === null ? '<span class="text-muted">—</span>' : e(format_date($st['entered_at'])) ?><?php if ($st['batches']): ?> <small class="text-muted"><?= e($st['batches']) ?></small><?php endif; ?></td>
                                <td id="<?= e($sid) ?>-actual-end"><?= $st['left_at'] === null ? ($st['entered_at'] !== null ? badge('In progress', 'info') : '<span class="text-muted">—</span>') : e(format_date($st['left_at'])) ?></td>
                                <td id="<?= e($sid) ?>-actual-days"><?= $st['actual_days'] === null ? '<span class="text-muted">—</span>' : e($st['actual_days']) ?></td>
                                <td id="<?= e($sid) ?>-variance" class="<?= $st['variance_days'] === null ? '' : ($st['variance_days'] > 0 ? 'text-warning' : 'text-success') ?>"><?= $st['variance_days'] === null ? '<span class="text-muted">—</span>' : e(($st['variance_days'] > 0 ? '+' : '') . $st['variance_days']) ?></td>
                                <td id="<?= e($sid) ?>-booked">
                                    <?php foreach ($st['bookings'] as $b): ?><div class="text-nowrap fs-12"><a <?= nav_attrs('/reservations/' . (int) $b['id']) ?>><?= e($b['resource_name']) ?></a> <span class="text-muted"><?= e(reservation_window_label($b)) ?></span></div><?php endforeach; ?>
                                    <?php if ($st['bookings'] === []): ?><span class="text-muted">—</span><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($processing['stages'] === []): ?><tr id="production-order-stage-empty"><td colspan="9" class="text-center text-muted py-4">The recipe has no stages.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer fs-12 text-muted" id="production-order-view-processing-footer">
                    <span id="production-order-view-processing-planned-days">Planned processing time: <strong><?= e($processing['planned_days']) ?> days</strong></span>
                    <?php if ($processing['planned_end'] !== null): ?> · <span id="production-order-view-processing-planned-end">ends <?= e(format_date($processing['planned_end'])) ?> from the pitch date</span><?php endif; ?>
                    <?php if ($order['planned_package_on']): ?> · <span id="production-order-view-processing-package-on">planned package date <?= e(format_date($order['planned_package_on'])) ?></span><?php endif; ?>
                    <?php if ($processing['elapsed_days'] !== null): ?> · <span id="production-order-view-processing-elapsed">elapsed <strong><?= e($processing['elapsed_days']) ?> days</strong></span><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-xl-5">
            <div class="card" id="production-order-view-outputs-planned">
                <div class="card-header"><h5 class="card-title">Outputs — planned</h5></div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="hstack justify-content-between mb-3"><span class="text-muted">Planned volume</span><span id="production-order-view-outputs-volume"><?= fmt_qty_html($outputs['planned_volume_l'], 'L') ?></span></li>
                        <li class="hstack justify-content-between mb-3"><span class="text-muted">Expected loss</span><span id="production-order-view-outputs-loss"><?= e(number_format($outputs['expected_loss_pct'], 1)) ?> %</span></li>
                        <li class="hstack justify-content-between mb-0"><span class="text-muted">Expected packaged volume</span><span id="production-order-view-outputs-packaged" class="fw-semibold"><?= fmt_qty_html($outputs['expected_packaged_l'], 'L') ?></span></li>
                    </ul>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="production-order-view-packages-table">
                        <thead class="thead-light"><tr><th>Package</th><th>Planned</th><th>For order</th></tr></thead>
                        <tbody>
                        <?php foreach ($outputs['packages'] as $pk): $pid = 'production-order-package-row-' . (int) $pk['id']; ?>
                            <tr id="<?= e($pid) ?>">
                                <td id="<?= e($pid) ?>-package"><a <?= nav_attrs('/packaging-configs/' . (int) $pk['configuration_id'] . '/edit') ?>><?= e($pk['package_name']) ?></a></td>
                                <td id="<?= e($pid) ?>-planned"><?= $pk['planned_units'] !== null ? e(number_format((int) $pk['planned_units'])) . ' units' . ($pk['units_per_case'] ? ' <small class="text-muted">(' . e(number_format((int) $pk['planned_units'] / (int) $pk['units_per_case'], 1)) . ' cases)</small>' : '') : e(number_format((float) $pk['share_pct'], 0)) . ' % of the volume' ?></td>
                                <td id="<?= e($pid) ?>-order"><?= $pk['sales_order_number'] !== null ? '<a ' . nav_attrs('/orders/' . (int) $pk['sales_order_id']) . '>' . e($pk['sales_order_number']) . '</a>' : '<span class="text-muted">Stock</span>' ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($outputs['packages'] === []): ?><tr id="production-order-package-empty"><td colspan="3" class="text-center text-muted py-3">No packaging planned yet: bulk, decide later.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-xl-7">
            <div class="card" id="production-order-view-outputs-actual">
                <div class="card-header"><h5 class="card-title">Outputs — actual</h5></div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="production-order-view-batches-table">
                        <thead class="thead-light"><tr><th>Batch</th><th>Status</th><th>Stage</th><th>Volume now</th><th>Started</th></tr></thead>
                        <tbody>
                        <?php foreach ($outputs['batches'] as $b): $bid = 'production-order-batch-row-' . (int) $b['id']; ?>
                            <tr id="<?= e($bid) ?>">
                                <td id="<?= e($bid) ?>-number"><a <?= nav_attrs('/batches/' . (int) $b['id']) ?>><?= e($b['number']) ?></a></td>
                                <td id="<?= e($bid) ?>-status"><?= status_badge($b['status']) ?></td>
                                <td id="<?= e($bid) ?>-stage"><?= e($b['stage_name']) ?></td>
                                <td id="<?= e($bid) ?>-volume"><?= fmt_qty_html($b['current_volume_l'], 'L') ?></td>
                                <td id="<?= e($bid) ?>-started"><?= e(format_date($b['started_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($outputs['batches'] === []): ?><tr id="production-order-batch-empty"><td colspan="5" class="text-center text-muted py-3">No batch started yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-responsive border-top">
                    <table class="table table-hover mb-0" id="production-order-view-runs-table">
                        <thead class="thead-light"><tr><th>Packaging run</th><th>Package</th><th>Status</th><th>Volume in</th><th>Units out</th><th>Loss</th></tr></thead>
                        <tbody>
                        <?php foreach ($outputs['runs'] as $r): $rid = 'production-order-run-row-' . (int) $r['id']; ?>
                            <tr id="<?= e($rid) ?>">
                                <td id="<?= e($rid) ?>-number"><a <?= nav_attrs('/packaging-runs/' . (int) $r['id']) ?>><?= e($r['number']) ?></a> <small class="text-muted"><?= e(format_date($r['run_on'])) ?></small></td>
                                <td id="<?= e($rid) ?>-package"><?= e($r['package_name']) ?></td>
                                <td id="<?= e($rid) ?>-status"><?= status_badge($r['status']) ?></td>
                                <td id="<?= e($rid) ?>-in"><?= $r['volume_in_l'] === null ? '<span class="text-muted">—</span>' : fmt_qty_html($r['volume_in_l'], 'L') ?></td>
                                <td id="<?= e($rid) ?>-units"><?= $r['units_out'] === null ? '<span class="text-muted">—</span>' : e(number_format((int) $r['units_out'])) ?></td>
                                <td id="<?= e($rid) ?>-loss"><?= $r['loss_l'] === null ? '<span class="text-muted">—</span>' : fmt_qty_html($r['loss_l'], 'L') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($outputs['runs'] === []): ?><tr id="production-order-run-empty"><td colspan="6" class="text-center text-muted py-3">No packaging run yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="table-responsive border-top">
                    <table class="table table-hover mb-0" id="production-order-view-lots-table">
                        <thead class="thead-light"><tr><th>Finished lot</th><th>Package</th><th>Packaged</th><th>Units packaged</th><th>Units on hand</th><th>Tax class</th></tr></thead>
                        <tbody>
                        <?php foreach ($outputs['lots'] as $l): $lid = 'production-order-lot-row-' . (int) $l['lot_id']; ?>
                            <tr id="<?= e($lid) ?>">
                                <td id="<?= e($lid) ?>-number"><a <?= nav_attrs('/finished-lots/' . (int) $l['lot_id']) ?>><?= e($l['lot_number']) ?></a></td>
                                <td id="<?= e($lid) ?>-package"><?= e($l['package_name']) ?></td>
                                <td id="<?= e($lid) ?>-packaged"><?= e(format_date($l['packaged_on'])) ?></td>
                                <td id="<?= e($lid) ?>-units"><?= e(number_format((int) $l['units_packaged'])) ?></td>
                                <td id="<?= e($lid) ?>-on-hand"><?= e(number_format((float) $l['units_on_hand'])) ?></td>
                                <td id="<?= e($lid) ?>-tax"><?= e(humanize($l['tax_class'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($outputs['lots'] === []): ?><tr id="production-order-lot-empty"><td colspan="6" class="text-center text-muted py-3">No finished lot yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php if ($status !== 'planned'): ?>
    <div class="row">
        <div class="col-lg-12">
            <div class="card" id="production-order-view-allocations">
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
