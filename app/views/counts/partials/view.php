<?php /** @var array $count  @var array $user  @var bool $reversed */
$id = (int) $count['id'];
$status = $count['status'];
$canCount = user_can($user, 'receiving') && in_array($status, ['open', 'counting'], true);
$varianceLines = count(array_filter($count['lines'], static fn($l) => $l['variance_base'] !== null && (float) $l['variance_base'] !== 0.0));
$actions = '';
if (in_array($status, ['open', 'counting', 'review'], true) && user_can($user, 'receiving')) {
    $actions .= '<button type="button" class="btn btn-light-brand" id="count-view-cancel-btn" hx-post="/counts/' . e($id) . '/cancel" hx-confirm="Cancel this count? Entered quantities are kept but nothing is posted." hx-target="#page-content" hx-swap="innerHTML"><i class="feather-x-circle me-2"></i><span>Cancel</span></button>';
}
if ($status === 'counting' && user_can($user, 'receiving')) {
    $actions .= '<button type="button" class="btn btn-primary" id="count-view-submit-btn" hx-post="/counts/' . e($id) . '/submit" hx-target="#page-content" hx-swap="innerHTML"><i class="feather-send me-2"></i><span>Submit for review</span></button>';
}
if ($status === 'review' && user_can($user)) {
    $actions .= '<button type="button" class="btn btn-primary" id="count-view-approve-btn" hx-post="/counts/' . e($id) . '/approve" hx-confirm="Approve this count? Variances will be posted to the ledger as count corrections." hx-target="#page-content" hx-swap="innerHTML"><i class="feather-check-circle me-2"></i><span>Approve</span></button>';
}
if ($status === 'approved' && user_can($user)) {
    $actions .= '<button type="button" class="btn btn-light-brand" id="count-view-reverse-btn" hx-post="/counts/' . e($id) . '/reverse" hx-confirm="Reverse ' . e($count['number']) . '? This posts compensating ledger rows." hx-target="#page-content" hx-swap="innerHTML"><i class="feather-rotate-ccw me-2"></i><span>Reverse</span></button>';
}
?>
<?= view('shared/page-header.php', ['title' => $count['number'], 'screen' => 'count-view', 'crumbs' => ['Inventory' => null, 'Counts' => '/counts/', $count['number'] => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="count-view-content">
    <div class="row">
        <div class="col-xxl-4 col-xl-6">
            <div class="card" id="count-view-summary">
                <div class="card-body">
                    <div class="mb-4 d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold mb-0"><?= e($count['number']) ?></h5>
                        <?= status_badge($status, 'count-view-status') ?>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-map-pin"></i>Location</span><span id="count-view-location"><?= e($count['location_name']) ?> <?= badge(humanize($count['location_tax_state']), $count['location_tax_state'] === 'bonded' ? 'info' : 'warning') ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-clipboard"></i>Kind</span><span id="count-view-kind"><?= e(COUNT_KINDS[$count['kind']] ?? $count['kind']) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-clock"></i>Started</span><span id="count-view-started"><?= e(format_datetime($count['started_at'])) ?> <small class="text-muted"><?= e($count['started_by_name'] ?? '') ?></small></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-list"></i>Lines</span><span id="count-view-lines"><?= e(count($count['lines'])) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-alert-triangle"></i>Variance lines</span><span id="count-view-variance-lines" class="<?= $varianceLines > 0 ? 'text-danger fw-semibold' : '' ?>"><?= e($varianceLines) ?></span></li>
                        <li class="hstack justify-content-between mb-0"><span class="text-muted fw-medium hstack gap-3"><i class="feather-check-circle"></i>Approved</span><span id="count-view-approved"><?= e($count['approved_at'] ? $count['approved_by_name'] . ', ' . format_datetime($count['approved_at']) : 'Not yet') ?></span></li>
                    </ul>
                    <?php if ($reversed): ?><p class="mt-4 mb-0 text-muted fs-12" id="count-view-reversed"><i class="feather-rotate-ccw me-1"></i>Reversed: compensating ledger rows were posted and this document is cancelled.</p><?php endif; ?>
                    <?php if ($count['notes']): ?><p class="mt-4 mb-0 text-muted fs-12" id="count-view-notes"><?= e($count['notes']) ?></p><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xxl-8 col-xl-6">
            <div class="card border-top-0" id="count-view-tabs-card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="count-view-tabs" role="tablist">
                        <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="count-view-tab-sheet" class="nav-link active" data-bs-toggle="tab" data-bs-target="#count-view-pane-sheet" role="tab">Count sheet</a></li>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="count-view-pane-sheet" role="tabpanel">
                        <?= view('counts/partials/sheet.php', ['count' => $count, 'lines' => $count['lines'], 'canEdit' => $canCount, 'addForm' => '']) ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
