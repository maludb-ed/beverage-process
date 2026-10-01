<?php /** @var array $keg  @var array $movements  @var array $lots  @var array $user  @var bool $deletable */
$id = (int) $keg['id'];
$canEdit = user_can($user, 'production');
$actions = '';
if ($canEdit) {
    $actions .= nav_button('keg-view-edit-btn', '/kegs/' . $id . '/edit', 'Edit', 'feather-edit', 'btn btn-light-brand');
    if ($deletable) {
        $actions .= '<button type="button" class="btn btn-light-brand" id="keg-view-delete-btn" hx-post="/kegs/' . e($id) . '/delete" hx-target="#page-content" hx-swap="innerHTML" hx-confirm="Delete keg ' . e($keg['serial']) . '?"><i class="feather-trash-2 me-2"></i><span>Delete</span></button>';
    }
}
?>
<?= view('shared/page-header.php', ['title' => $keg['serial'], 'screen' => 'keg-view', 'crumbs' => ['Packaging' => null, 'Kegs' => '/kegs/', $keg['serial'] => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="keg-view-content">
    <div class="row">
        <div class="col-xxl-4 col-xl-6">
            <div class="card stretch stretch-full" id="keg-view-summary">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0"><?= e($keg['serial']) ?></h5></div>
                    <ul class="list-unstyled mb-0">
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-droplet"></i>Size</span><span id="keg-view-size"><?= fmt_qty_html($keg['size_l'], 'L', 2) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-users"></i>Ownership</span><span id="keg-view-ownership"><?= e(KEG_OWNERSHIPS[$keg['ownership']] ?? $keg['ownership']) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-dollar-sign"></i>Deposit</span><span id="keg-view-deposit">$<?= e(number_format((float) $keg['deposit_amount'], 2)) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-repeat"></i>Fills</span><span id="keg-view-fill-count"><?= e($keg['fill_count']) ?></span></li>
                        <li class="hstack justify-content-between mb-0"><span class="text-muted fw-medium hstack gap-3"><i class="feather-check-circle"></i>Last cleaned</span><span id="keg-view-last-cleaned"><?= e($keg['last_cleaned_at'] ? format_datetime($keg['last_cleaned_at']) : '—') ?></span></li>
                    </ul>
                    <?php if ($keg['notes']): ?><p class="mt-4 mb-0 text-muted fs-12" id="keg-view-notes"><?= e($keg['notes']) ?></p><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xxl-8 col-xl-6">
            <?= view('kegs/partials/state-card.php', ['keg' => $keg, 'movements' => $movements, 'lots' => $lots, 'canEdit' => $canEdit, 'errors' => [], 'notice' => null, 'input' => []]) ?>
        </div>
    </div>
</div>
