<?php /** @var array $version  @var array $lines  @var float $scaleL  @var float $scaleDisplay  @var array $user  @var array $otherVersions  @var ?array $overheadRate  @var ?string $alert */
$id = (int) $version['id'];
$status = $version['status'];
$canProduction = user_can($user, 'production');
$label = $version['product_name'] . ' v' . $version['version_no'];
$actions = '';
if ($canProduction) {
    if ($status === 'draft') {
        $actions .= nav_button('recipe-view-edit-btn', '/recipes/' . $id . '/edit', 'Edit', 'feather-edit', 'btn btn-light-brand');
        $actions .= '<button type="button" class="btn btn-primary" id="recipe-view-activate-btn" hx-post="/recipes/' . e($id) . '/activate" hx-target="#page-content" hx-swap="innerHTML"><i class="feather-check-circle me-2"></i><span>Activate</span></button>';
    }
    $actions .= nav_button('recipe-view-copy-btn', '/products/' . (int) $version['product_id'] . '/recipes/new?copy_from=' . $id, 'New version from this', 'feather-copy', 'btn btn-light-brand');
}
$perGal = $version['standard_cost_per_l'] === null ? null : (float) $version['standard_cost_per_l'] * unit_factor(display_unit('L'));
?>
<?= view('shared/page-header.php', ['title' => $label, 'screen' => 'recipe-view', 'crumbs' => ['Products' => null, 'Products and recipes' => '/products/', $version['product_name'] => '/products/' . (int) $version['product_id'], 'v' . $version['version_no'] => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="recipe-view-content">
    <?php if ($alert): ?><div class="alert alert-danger" role="alert" id="recipe-view-alert"><?= e($alert) ?></div><?php endif; ?>
    <div class="row">
        <div class="col-xxl-4 col-xl-6">
            <div class="card stretch stretch-full" id="recipe-view-summary">
                <div class="card-body">
                    <div class="mb-4 d-flex align-items-center justify-content-between">
                        <div><h5 class="fw-bold mb-1">Version <?= e($version['version_no']) ?></h5><div class="fs-12 text-muted"><a <?= nav_attrs('/products/' . (int) $version['product_id']) ?>><?= e($version['product_name']) ?></a></div></div>
                        <?= status_badge($status, 'recipe-view-status') ?>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-droplet"></i>Batch volume</span><span id="recipe-view-batch-volume" class="fw-semibold"><?= fmt_qty_html($version['target_batch_volume_l'], 'L', 1) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-trending-down"></i>Expected total loss</span><span id="recipe-view-total-loss"><?= $version['expected_total_loss_pct'] === null ? '<span class="text-muted">set at activation</span>' : e(number_format((float) $version['expected_total_loss_pct'], 2)) . '%' ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-dollar-sign"></i>Standard cost</span><span id="recipe-view-cost-total"><?= $version['standard_cost_total'] === null ? '<span class="text-muted">set at activation</span>' : '$' . e(number_format((float) $version['standard_cost_total'], 2)) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-dollar-sign"></i>Per <?= e(display_unit('L')) ?> / per L</span><span id="recipe-view-cost-per-unit"><?= $perGal === null ? '' : '$' . e(number_format($perGal, 4)) . ' / ' . e(display_unit('L')) . ' ($' . e(number_format((float) $version['standard_cost_per_l'], 4)) . ' / L)' ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-check-circle"></i>Activated</span><span id="recipe-view-activated"><?= $version['activated_at'] ? e(format_datetime($version['activated_at'])) . ' <small class="text-muted">' . e($version['activated_by_name'] ?? '') . '</small>' : 'Not yet' ?></span></li>
                        <li class="hstack justify-content-between mb-0"><span class="text-muted fw-medium hstack gap-3"><i class="feather-percent"></i>Stages / lines</span><span id="recipe-view-counts"><?= e(count($version['stages'])) ?> / <?= e(count($version['lines'])) ?></span></li>
                    </ul>
                    <?php if ($status !== 'draft' && $overheadRate === null): ?><p class="mt-4 mb-0 text-warning fs-12" id="recipe-view-overhead-note">Overhead not included: no current overhead rate applies to this beverage's premises.</p><?php endif; ?>
                    <?php if ($version['change_note']): ?><p class="mt-4 mb-0 text-muted fs-12" id="recipe-view-change-note"><?= e($version['change_note']) ?></p><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xxl-8 col-xl-6">
            <div class="card stretch stretch-full" id="recipe-view-stages-card">
                <div class="card-header"><h5 class="card-title">Stages</h5></div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="recipe-view-stages-table">
                        <thead class="thead-light"><tr><th>#</th><th>Stage</th><th>Expected loss</th><th>Days</th><th>Instructions</th></tr></thead>
                        <tbody>
                        <?php foreach ($version['stages'] as $stage): $sid = (int) $stage['id']; ?>
                            <tr id="recipe-stage-view-row-<?= e($sid) ?>">
                                <td><?= e($stage['seq']) ?></td>
                                <td id="recipe-stage-view-row-<?= e($sid) ?>-stage"><?= e($stage['stage_name']) ?></td>
                                <td id="recipe-stage-view-row-<?= e($sid) ?>-loss"><?= e(number_format((float) $stage['expected_loss_pct'], 2)) ?>%</td>
                                <td id="recipe-stage-view-row-<?= e($sid) ?>-days"><?= e($stage['expected_duration_days']) ?></td>
                                <td id="recipe-stage-view-row-<?= e($sid) ?>-instructions"><?= e($stage['instructions']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($version['stages'] === []): ?><tr id="recipe-view-stages-empty"><td colspan="5" class="text-center text-muted py-4">No stages yet.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card stretch stretch-full" id="recipe-view-lines-card">
                <div class="card-header">
                    <h5 class="card-title">Lines</h5>
                    <div class="input-group w-auto">
                        <span class="input-group-text" id="recipe-view-scale-label">Scale to</span>
                        <input type="number" min="0" step="any" class="form-control" name="scale" id="recipe-view-scale-volume" aria-labelledby="recipe-view-scale-label" value="<?= e($scaleDisplay) ?>"
                               hx-get="/recipes/<?= e($id) ?>" hx-trigger="input changed delay:400ms, change" hx-target="#recipe-view-lines-table" hx-swap="outerHTML" />
                        <span class="input-group-text"><?= e(display_unit('L')) ?></span>
                    </div>
                </div>
                <?= view('recipes/partials/lines-table.php', ['version' => $version, 'lines' => $lines, 'scaleL' => $scaleL, 'scaleDisplay' => $scaleDisplay]) ?>
            </div>
            <div class="card stretch stretch-full" id="recipe-view-diff-card">
                <div class="card-header">
                    <h5 class="card-title">Compare</h5>
                    <select class="form-select w-auto" name="other" id="recipe-view-diff-version" aria-label="Compare with version"
                            hx-get="/recipes/<?= e($id) ?>/diff" hx-trigger="change" hx-target="#recipe-view-diff" hx-swap="innerHTML">
                        <option value="">Compare with…</option>
                        <?php foreach ($otherVersions as $vid => $vlabel): ?><option value="<?= e($vid) ?>"><?= e($vlabel) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="card-body" id="recipe-view-diff"><p class="text-muted mb-0" id="recipe-view-diff-empty">Choose another version to compare with.</p></div>
            </div>
        </div>
    </div>
</div>
