<?php /** @var array $product  @var array $user  @var string $activeTab */
$id = (int) $product['id'];
$can = static fn(string ...$roles) => user_can($user, ...$roles);
$canProduction = $can('production');
$active = $product['active_recipe'];
$actions = '';
if ($canProduction && $product['status'] !== 'retired') {
    $actions .= nav_button('product-view-edit-btn', '/products/' . $id . '/edit', 'Edit', 'feather-edit', 'btn btn-light-brand');
    $actions .= '<button type="button" class="btn btn-light-brand" id="product-view-retire-btn" hx-post="/products/' . e($id) . '/retire" hx-target="#page-content" hx-swap="innerHTML" hx-confirm="Retire ' . e($product['name']) . '?"><i class="feather-archive me-2"></i><span>Retire</span></button>';
}
$tabs = [
    'recipes' => 'Recipes (' . count($product['recipes']) . ')', 'packaging' => 'Packaging (' . count($product['packaging']) . ')',
    'specs' => 'Specs (' . count($product['specs']) . ')', 'approvals' => 'Approvals (' . count($product['approvals']) . ')', 'batches' => 'Batches (' . count($product['batches']) . ')',
];
?>
<?= view('shared/page-header.php', ['title' => $product['name'], 'screen' => 'product-view', 'crumbs' => ['Products' => null, 'Products and recipes' => '/products/', $product['name'] => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="product-view-content">
    <div class="row">
        <div class="col-xxl-4 col-xl-6">
            <div class="card" id="product-view-summary">
                <div class="card-body">
                    <div class="mb-4 d-flex align-items-center justify-content-between">
                        <div><h5 class="fw-bold mb-1"><?= e($product['name']) ?></h5><div class="fs-12 text-muted"><?= e($product['code']) ?></div></div>
                        <?= status_badge($product['status'], 'product-view-status') ?>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-droplet"></i>Beverage</span><span id="product-view-beverage"><?= e(PRODUCT_BEVERAGES[$product['beverage_type']] ?? $product['beverage_type']) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-tag"></i>Style</span><span id="product-view-style"><?= e($product['style'] ?: '—') ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-dollar-sign"></i>Intended tax class</span><span id="product-view-tax-class"><?= e(PRODUCT_TAX_CLASSES[$product['intended_tax_class']] ?? $product['intended_tax_class']) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-percent"></i>Target ABV</span><span id="product-view-abv"><?= $product['target_abv'] === null ? '—' : e(number_format((float) $product['target_abv'], 2)) . '%' ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-percent"></i>Fruit share</span><span id="product-view-fruit-share"><?= $product['target_fruit_share_pct'] === null ? '—' : e(rtrim(rtrim(number_format((float) $product['target_fruit_share_pct'], 2), '0'), '.')) . '%' ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-alert-circle"></i>Other fruit / flavoring</span><span id="product-view-flags"><?= e(yes_no($product['contains_other_fruit'])) ?> / <?= e(yes_no($product['contains_flavoring'])) ?></span></li>
                        <li class="hstack justify-content-between mb-0"><span class="text-muted fw-medium hstack gap-3"><i class="feather-book-open"></i>Active recipe</span><span id="product-view-active-recipe"><?php if ($active): ?><a <?= nav_attrs('/recipes/' . (int) $active['id']) ?>>v<?= e($active['version_no']) ?></a><?php else: ?><span class="text-muted">none</span><?php endif; ?></span></li>
                    </ul>
                    <?php if ($product['notes']): ?><p class="mt-4 mb-0 text-muted fs-12" id="product-view-notes"><?= e($product['notes']) ?></p><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xxl-8 col-xl-6">
            <div class="card border-top-0" id="product-view-tabs-card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="product-view-tabs" role="tablist">
                        <?php foreach ($tabs as $tab => $label): ?>
                            <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="product-view-tab-<?= e($tab) ?>" class="nav-link<?= $tab === $activeTab ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#product-view-pane-<?= e($tab) ?>" role="tab"><?= e($label) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade<?= $activeTab === 'recipes' ? ' show active' : '' ?>" id="product-view-pane-recipes" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="product-view-recipes-table">
                                <thead class="thead-light"><tr><th>Version</th><th>Batch volume</th><th>Expected loss</th><th>Standard cost</th><th>Activated</th><th>Change note</th></tr></thead>
                                <tbody>
                                <?php foreach ($product['recipes'] as $recipe): $rid = (int) $recipe['id']; ?>
                                    <tr id="product-recipe-row-<?= e($rid) ?>">
                                        <td id="product-recipe-row-<?= e($rid) ?>-version"><a <?= nav_attrs('/recipes/' . $rid) ?>><?= status_dot(status_color($recipe['status'])) ?>v<?= e($recipe['version_no']) ?></a> <?= status_badge($recipe['status']) ?></td>
                                        <td id="product-recipe-row-<?= e($rid) ?>-volume"><?= fmt_qty_html($recipe['target_batch_volume_l'], 'L', 1) ?></td>
                                        <td id="product-recipe-row-<?= e($rid) ?>-loss"><?= $recipe['expected_total_loss_pct'] === null ? '' : e(number_format((float) $recipe['expected_total_loss_pct'], 2)) . '%' ?></td>
                                        <td id="product-recipe-row-<?= e($rid) ?>-cost"><?= $recipe['standard_cost_total'] === null ? '' : '$' . e(number_format((float) $recipe['standard_cost_total'], 2)) ?></td>
                                        <td id="product-recipe-row-<?= e($rid) ?>-activated"><?= e(format_datetime($recipe['activated_at'])) ?> <small class="text-muted"><?= e($recipe['activated_by_name'] ?? '') ?></small></td>
                                        <td id="product-recipe-row-<?= e($rid) ?>-note"><?= e($recipe['change_note']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($product['recipes'] === []): ?><tr id="product-view-recipes-empty"><td colspan="6" class="text-center text-muted py-4">No recipe versions yet.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($canProduction && $product['status'] !== 'retired'): ?>
                            <div class="p-4 border-top text-end"><?= nav_button('product-view-new-recipe-btn', '/products/' . $id . '/recipes/new', 'New version') ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="tab-pane fade<?= $activeTab === 'packaging' ? ' show active' : '' ?>" id="product-view-pane-packaging" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="product-view-packaging-table">
                                <thead class="thead-light"><tr><th>Name</th><th>Kind</th><th>Fill volume</th><th>Units per case</th><th>Expected loss</th><th>Active</th></tr></thead>
                                <tbody>
                                <?php foreach ($product['packaging'] as $config): $cid = (int) $config['id']; ?>
                                    <tr id="product-packaging-row-<?= e($cid) ?>">
                                        <td id="product-packaging-row-<?= e($cid) ?>-name"><a <?= nav_attrs('/packaging-configs/' . $cid . '/edit') ?>><?= status_dot($config['active'] ? 'success' : 'secondary') ?><?= e($config['name']) ?></a> <small class="text-muted"><?= e($config['item_code']) ?></small></td>
                                        <td id="product-packaging-row-<?= e($cid) ?>-kind"><?= e(humanize($config['package_kind'])) ?></td>
                                        <td id="product-packaging-row-<?= e($cid) ?>-fill"><?= fmt_qty_html($config['fill_volume_l'], 'L', 3) ?></td>
                                        <td id="product-packaging-row-<?= e($cid) ?>-units"><?= e($config['units_per_case']) ?></td>
                                        <td id="product-packaging-row-<?= e($cid) ?>-loss"><?= e(number_format((float) $config['expected_loss_pct'], 2)) ?>%</td>
                                        <td id="product-packaging-row-<?= e($cid) ?>-active"><?= badge($config['active'] ? 'Active' : 'Inactive', $config['active'] ? 'success' : 'secondary') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($product['packaging'] === []): ?><tr id="product-view-packaging-empty"><td colspan="6" class="text-center text-muted py-4">No packaging configurations yet.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($canProduction): ?>
                            <div class="p-4 border-top text-end"><?= nav_button('product-view-add-packaging-btn', '/packaging-configs/new?product=' . rawurlencode($product['code']), 'Add packaging') ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="tab-pane fade<?= $activeTab === 'specs' ? ' show active' : '' ?>" id="product-view-pane-specs" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="product-view-specs-table">
                                <thead class="thead-light"><tr><th>Stage</th><th>Measurement</th><th>Min</th><th>Max</th><th>Target</th></tr></thead>
                                <tbody>
                                <?php $lastStage = null; foreach ($product['specs'] as $spec): $sid = (int) $spec['id']; ?>
                                    <tr id="product-spec-row-<?= e($sid) ?>">
                                        <td id="product-spec-row-<?= e($sid) ?>-stage"><?= $lastStage === $spec['stage_code'] ? '' : e($spec['stage_name']) ?></td>
                                        <td id="product-spec-row-<?= e($sid) ?>-measurement"><a <?= nav_attrs('/specs/' . $sid . '/edit') ?>><?= status_dot($spec['active'] ? 'success' : 'secondary') ?><?= e($spec['measurement_name']) ?></a> <small class="text-muted"><?= e($spec['measurement_unit']) ?></small></td>
                                        <td><?= e($spec['min_value'] === null ? '' : (float) $spec['min_value']) ?></td>
                                        <td><?= e($spec['max_value'] === null ? '' : (float) $spec['max_value']) ?></td>
                                        <td><?= e($spec['target_value'] === null ? '' : (float) $spec['target_value']) ?></td>
                                    </tr>
                                <?php $lastStage = $spec['stage_code']; endforeach; ?>
                                <?php if ($product['specs'] === []): ?><tr id="product-view-specs-empty"><td colspan="5" class="text-center text-muted py-4">No specs yet.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="p-4 border-top text-end"><?= nav_button('product-view-open-specs-btn', '/products/' . $id . '/specs', 'Open specs', 'feather-list', 'btn btn-light-brand') ?></div>
                    </div>
                    <div class="tab-pane fade<?= $activeTab === 'approvals' ? ' show active' : '' ?>" id="product-view-pane-approvals" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="product-view-approvals-table">
                                <thead class="thead-light"><tr><th>Kind</th><th>Reference</th><th>Status</th><th>Approved</th><th>Expires</th></tr></thead>
                                <tbody>
                                <?php foreach ($product['approvals'] as $approval): $aid = (int) $approval['id']; ?>
                                    <tr id="product-approval-row-<?= e($aid) ?>">
                                        <td id="product-approval-row-<?= e($aid) ?>-kind"><a <?= nav_attrs('/approvals/' . $aid . '/edit') ?>><?= status_dot(approval_status_color($approval['status'])) ?><?= e(humanize($approval['kind'])) ?></a><?= $approval['package_name'] ? ' <small class="text-muted">' . e($approval['package_name']) . '</small>' : '' ?></td>
                                        <td id="product-approval-row-<?= e($aid) ?>-reference"><?= e($approval['reference_no']) ?></td>
                                        <td id="product-approval-row-<?= e($aid) ?>-status"><?= approval_status_badge($approval['status']) ?></td>
                                        <td id="product-approval-row-<?= e($aid) ?>-approved-on"><?= e(format_date($approval['approved_on'])) ?></td>
                                        <td id="product-approval-row-<?= e($aid) ?>-expires-on"><?= e(format_date($approval['expires_on'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($product['approvals'] === []): ?><tr id="product-view-approvals-empty"><td colspan="5" class="text-center text-muted py-4">No approvals recorded.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php if ($can('compliance')): ?>
                            <div class="p-4 border-top text-end"><?= nav_button('product-view-add-approval-btn', '/approvals/new?product=' . rawurlencode($product['code']), 'Record approval') ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="tab-pane fade<?= $activeTab === 'batches' ? ' show active' : '' ?>" id="product-view-pane-batches" role="tabpanel">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="product-view-batches-table">
                                <thead class="thead-light"><tr><th>Batch</th><th>Status</th><th>Stage</th><th>Started</th></tr></thead>
                                <tbody>
                                <?php foreach ($product['batches'] as $batch): $bid = (int) $batch['id']; ?>
                                    <tr id="product-batch-row-<?= e($bid) ?>">
                                        <td id="product-batch-row-<?= e($bid) ?>-number"><a <?= nav_attrs('/batches/' . $bid) ?>><?= e($batch['number']) ?></a></td>
                                        <td id="product-batch-row-<?= e($bid) ?>-status"><?= status_badge($batch['status']) ?></td>
                                        <td id="product-batch-row-<?= e($bid) ?>-stage"><?= e(humanize($batch['current_stage_code'])) ?></td>
                                        <td id="product-batch-row-<?= e($bid) ?>-started"><?= e(format_datetime($batch['started_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if ($product['batches'] === []): ?><tr id="product-view-batches-empty"><td colspan="4" class="text-center text-muted py-4">No batches yet.</td></tr><?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
