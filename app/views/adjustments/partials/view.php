<?php /** @var array $adjustment  @var array $user  @var bool $reversed */
$id = (int) $adjustment['id'];
$status = $adjustment['status'];
$writeDown = array_filter($adjustment['lines'], static fn($l) => (float) $l['qty_delta_base'] < 0) !== [];
$actions = '';
if (in_array($status, ['draft', 'pending_approval'], true) && user_can($user, 'receiving')) {
    if ($status === 'draft') {
        $actions .= nav_button('adjustment-view-edit-btn', '/adjustments/' . $id . '/edit', 'Edit', 'feather-edit', 'btn btn-light-brand');
    }
    $actions .= '<button type="button" class="btn btn-light-brand" id="adjustment-view-cancel-btn" hx-post="/adjustments/' . e($id) . '/cancel" hx-confirm="Cancel this adjustment?" hx-target="#page-content" hx-swap="innerHTML"><i class="feather-x-circle me-2"></i><span>Cancel</span></button>';
}
if ($status === 'pending_approval' && user_can($user)) {
    $actions .= '<button type="button" class="btn btn-primary" id="adjustment-view-approve-btn" hx-post="/adjustments/' . e($id) . '/approve" hx-target="#page-content" hx-swap="innerHTML"><i class="feather-check-square me-2"></i><span>Approve</span></button>';
}
if ($status === 'draft' && user_can($user, 'receiving')) {
    $actions .= '<button type="button" class="btn btn-primary" id="adjustment-view-post-btn" hx-post="/adjustments/' . e($id) . '/post"'
        . ($writeDown ? ' hx-confirm="This adjustment writes stock down. Post it?"' : '') . ' hx-target="#page-content" hx-swap="innerHTML"><i class="feather-check-circle me-2"></i><span>Post adjustment</span></button>';
}
$threshold = $adjustment['requires_approval_above'];
if ($status === 'posted' && user_can($user, 'receiving')) {
    $actions .= '<button type="button" class="btn btn-light-brand" id="adjustment-view-reverse-btn" hx-post="/adjustments/' . e($id) . '/reverse" hx-confirm="Reverse ' . e($adjustment['number']) . '? This posts compensating ledger rows." hx-target="#page-content" hx-swap="innerHTML"><i class="feather-rotate-ccw me-2"></i><span>Reverse</span></button>';
}
?>
<?= view('shared/page-header.php', ['title' => $adjustment['number'], 'screen' => 'adjustment-view', 'crumbs' => ['Inventory' => null, 'Adjustments' => '/adjustments/', $adjustment['number'] => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="adjustment-view-content">
    <div class="row">
        <div class="col-xxl-4 col-xl-6">
            <div class="card stretch stretch-full" id="adjustment-view-summary">
                <div class="card-body">
                    <div class="mb-4 d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold mb-0"><?= e($adjustment['number']) ?></h5>
                        <?= status_badge($status, 'adjustment-view-status') ?>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-map-pin"></i>Location</span><span id="adjustment-view-location"><?= e($adjustment['location_name']) ?> <?= badge(humanize($adjustment['location_tax_state']), $adjustment['location_tax_state'] === 'bonded' ? 'info' : 'warning') ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-tag"></i>Reason</span><span id="adjustment-view-reason"><?= e($adjustment['reason_code']) ?> <small class="text-muted"><?= e($adjustment['reason_name']) ?></small></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-clock"></i>Adjusted</span><span id="adjustment-view-adjusted-at"><?= e(format_datetime($adjustment['adjusted_at'])) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-user"></i>Created by</span><span id="adjustment-view-created-by"><?= e($adjustment['created_by_name'] ?? '') ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-check-square"></i>Approval</span><span id="adjustment-view-approval"><?= e($adjustment['approved_at'] ? $adjustment['approved_by_name'] . ', ' . format_datetime($adjustment['approved_at']) : ($threshold !== null ? 'Required above ' . format_qty($threshold, 2) . ' (base units)' : 'Not required')) ?></span></li>
                        <li class="hstack justify-content-between mb-0"><span class="text-muted fw-medium hstack gap-3"><i class="feather-check-circle"></i>Posted</span><span id="adjustment-view-posted"><?= e($adjustment['posted_at'] ? $adjustment['posted_by_name'] . ', ' . format_datetime($adjustment['posted_at']) : 'Not yet') ?></span></li>
                    </ul>
                    <?php if ($reversed): ?><p class="mt-4 mb-0 text-muted fs-12" id="adjustment-view-reversed"><i class="feather-rotate-ccw me-1"></i>Reversed: compensating ledger rows were posted and this document is cancelled.</p><?php endif; ?>
                    <?php if ($adjustment['notes']): ?><p class="mt-4 mb-0 text-muted fs-12" id="adjustment-view-notes"><?= e($adjustment['notes']) ?></p><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xxl-8 col-xl-6">
            <div class="card border-top-0" id="adjustment-view-tabs-card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="adjustment-view-tabs" role="tablist">
                        <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="adjustment-view-tab-lines" class="nav-link active" data-bs-toggle="tab" data-bs-target="#adjustment-view-pane-lines" role="tab">Lines</a></li>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="adjustment-view-pane-lines" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="adjustment-view-lines-table">
                                <thead class="thead-light"><tr><th>Item</th><th>Lot</th><th>Change</th><th>Unit cost</th><th>Note</th></tr></thead>
                                <tbody>
                                <?php foreach ($adjustment['lines'] as $line): $lid = (int) $line['id']; $kind = inventory_unit_kind($line['item_class']); $delta = (float) $line['qty_delta_base']; ?>
                                    <tr id="adjustment-line-row-<?= e($lid) ?>">
                                        <td id="adjustment-line-row-<?= e($lid) ?>-item"><?= e($line['item_code']) ?> <small class="text-muted"><?= e($line['item_name']) ?></small></td>
                                        <td id="adjustment-line-row-<?= e($lid) ?>-lot"><a <?= nav_attrs('/lots/' . (int) $line['lot_id']) ?>><?= status_dot(status_color($line['quality_status'])) ?><?= e($line['lot_number']) ?></a></td>
                                        <td id="adjustment-line-row-<?= e($lid) ?>-qty" class="fw-semibold <?= $delta < 0 ? 'text-danger' : '' ?>"><?= $delta > 0 ? '+' : '' ?><?= fmt_qty_html($delta, $line['base_unit_code'], 1, $kind) ?></td>
                                        <td id="adjustment-line-row-<?= e($lid) ?>-unit-cost"><?= e(fmt_unit_cost($line['unit_cost_base'] ?? $line['lot_unit_cost_base'], $line['base_unit_code'], $kind)) ?><?= $line['unit_cost_base'] === null ? ' <small class="text-muted">(lot cost)</small>' : '' ?></td>
                                        <td id="adjustment-line-row-<?= e($lid) ?>-note"><?= e($line['note'] ?? '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($adjustment['lines'] === []): ?><tr><td colspan="5" class="text-center text-muted py-4">No lines yet.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
