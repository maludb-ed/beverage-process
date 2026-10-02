<?php /** @var array $transfer  @var array $user  @var bool $reversed */
$id = (int) $transfer['id'];
$status = $transfer['status'];
$actions = '';
if ($status === 'draft' && user_can($user, 'receiving')) {
    $actions .= nav_button('transfer-view-edit-btn', '/transfers/' . $id . '/edit', 'Edit', 'feather-edit', 'btn btn-light-brand');
    $actions .= '<button type="button" class="btn btn-light-brand" id="transfer-view-cancel-btn" hx-post="/transfers/' . e($id) . '/cancel" hx-confirm="Cancel this draft transfer?" hx-target="#page-content" hx-swap="innerHTML"><i class="feather-x-circle me-2"></i><span>Cancel</span></button>';
    $actions .= '<button type="button" class="btn btn-primary" id="transfer-view-post-btn" hx-post="/transfers/' . e($id) . '/post" hx-target="#page-content" hx-swap="innerHTML"><i class="feather-check-circle me-2"></i><span>Post transfer</span></button>';
}
if ($status === 'posted' && user_can($user, 'receiving')) {
    $actions .= '<button type="button" class="btn btn-light-brand" id="transfer-view-reverse-btn" hx-post="/transfers/' . e($id) . '/reverse" hx-confirm="Reverse ' . e($transfer['number']) . '? This posts compensating ledger rows." hx-target="#page-content" hx-swap="innerHTML"><i class="feather-rotate-ccw me-2"></i><span>Reverse</span></button>';
}
?>
<?= view('shared/page-header.php', ['title' => $transfer['number'], 'screen' => 'transfer-view', 'crumbs' => ['Inventory' => null, 'Transfers' => '/transfers/', $transfer['number'] => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="transfer-view-content">
    <div class="row">
        <div class="col-xxl-4 col-xl-6">
            <div class="card" id="transfer-view-summary">
                <div class="card-body">
                    <div class="mb-4 d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold mb-0"><?= e($transfer['number']) ?></h5>
                        <?= status_badge($status, 'transfer-view-status') ?>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-log-out"></i>From</span><span id="transfer-view-from"><?= e($transfer['from_name']) ?> <?= badge(humanize($transfer['from_tax_state']), $transfer['from_tax_state'] === 'bonded' ? 'info' : 'warning') ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-log-in"></i>To</span><span id="transfer-view-to"><?= e($transfer['to_name']) ?> <?= badge(humanize($transfer['to_tax_state']), $transfer['to_tax_state'] === 'bonded' ? 'info' : 'warning') ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-clock"></i>Transferred</span><span id="transfer-view-transferred-at"><?= e(format_datetime($transfer['transferred_at'])) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-user"></i>Created by</span><span id="transfer-view-created-by"><?= e($transfer['created_by_name'] ?? '') ?></span></li>
                        <li class="hstack justify-content-between mb-0"><span class="text-muted fw-medium hstack gap-3"><i class="feather-check-circle"></i>Posted</span><span id="transfer-view-posted"><?= e($transfer['posted_at'] ? $transfer['posted_by_name'] . ', ' . format_datetime($transfer['posted_at']) : 'Not yet') ?></span></li>
                    </ul>
                    <?php if ($reversed): ?><p class="mt-4 mb-0 text-muted fs-12" id="transfer-view-reversed"><i class="feather-rotate-ccw me-1"></i>Reversed: compensating ledger rows were posted and this document is cancelled.</p><?php endif; ?>
                    <?php if ($transfer['notes']): ?><p class="mt-4 mb-0 text-muted fs-12" id="transfer-view-notes"><?= e($transfer['notes']) ?></p><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xxl-8 col-xl-6">
            <div class="card border-top-0" id="transfer-view-tabs-card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="transfer-view-tabs" role="tablist">
                        <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="transfer-view-tab-lines" class="nav-link active" data-bs-toggle="tab" data-bs-target="#transfer-view-pane-lines" role="tab">Lines</a></li>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="transfer-view-pane-lines" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="transfer-view-lines-table">
                                <thead class="thead-light"><tr><th>Item</th><th>Lot</th><th>Quantity</th></tr></thead>
                                <tbody>
                                <?php foreach ($transfer['lines'] as $line): $lid = (int) $line['id']; ?>
                                    <tr id="transfer-line-row-<?= e($lid) ?>">
                                        <td id="transfer-line-row-<?= e($lid) ?>-item"><?= e($line['item_code']) ?> <small class="text-muted"><?= e($line['item_name']) ?></small></td>
                                        <td id="transfer-line-row-<?= e($lid) ?>-lot"><a <?= nav_attrs('/lots/' . (int) $line['lot_id']) ?>><?= status_dot(status_color($line['quality_status'])) ?><?= e($line['lot_number']) ?></a></td>
                                        <td id="transfer-line-row-<?= e($lid) ?>-qty" class="fw-semibold"><?= fmt_qty_html($line['qty_base'], $line['base_unit_code'], 1, inventory_unit_kind($line['item_class'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($transfer['lines'] === []): ?><tr><td colspan="3" class="text-center text-muted py-4">No lines yet.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
