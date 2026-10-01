<?php /** @var array $result  @var array $query  @var bool $canEdit  @var array $products  @var array $missing */
$inc = static fn(string $html, string $others) => str_replace('hx-include="#approvals-list-search"', 'hx-include="#approvals-list-search, ' . $others . '"', $html);
$kindFilter = $inc(list_filter('approvals-list', 'kind', '/approvals/', APPROVAL_KINDS, $query['kind'] ?? '', 'All kinds'), '#approvals-list-filter-status, #approvals-list-filter-product-id');
$statusFilter = $inc(list_filter('approvals-list', 'status', '/approvals/', APPROVAL_STATUSES, $query['status'] ?? '', 'All statuses'), '#approvals-list-filter-kind, #approvals-list-filter-product-id');
$productFilter = $inc(list_filter('approvals-list', 'product_id', '/approvals/', $products, (string) ($query['product_id'] ?? ''), 'All products'), '#approvals-list-filter-kind, #approvals-list-filter-status');
$actions = list_search('approvals-list', '/approvals/', $query['q'], 'Search product or reference', '#approvals-list-filter-kind, #approvals-list-filter-status, #approvals-list-filter-product-id')
    . $kindFilter . $statusFilter . $productFilter
    . ($canEdit ? nav_button('approvals-list-add-btn', '/approvals/new', 'Record Approval') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Approvals', 'screen' => 'approvals-list', 'crumbs' => ['Products' => null, 'Approvals' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="approvals-list-content">
    <?php if ($missing !== []): ?>
    <div class="row">
        <div class="col-lg-12">
            <div class="card stretch stretch-full" id="approvals-missing-card">
                <div class="card-header"><h5 class="card-title">Missing approvals</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="approvals-missing-table">
                            <thead class="thead-light"><tr><th>Product</th><th>Kind</th><th class="text-end">Actions</th></tr></thead>
                            <tbody>
                            <?php foreach ($missing as $i => $row): $m = 'approvals-missing-row-' . $row['product_id'] . '-' . $row['kind']; ?>
                                <tr id="<?= e($m) ?>">
                                    <td id="<?= e($m) ?>-product"><a <?= nav_attrs('/products/' . (int) $row['product_id']) ?>><?= status_dot('warning') ?><?= e($row['product_name']) ?></a> <small class="text-muted"><?= e($row['product_code']) ?></small></td>
                                    <td id="<?= e($m) ?>-kind"><?= e(humanize($row['kind'])) ?> <?= badge('Missing', 'warning') ?></td>
                                    <td id="<?= e($m) ?>-actions" class="text-end"><?php if ($canEdit): ?><?= nav_button($m . '-record-btn', '/approvals/new?product=' . rawurlencode($row['product_code']) . '&kind=' . rawurlencode($row['kind']), 'Record', 'feather-plus', 'btn btn-sm btn-light-brand') ?><?php endif; ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="row">
        <?= view('approvals/partials/table.php', ['result' => $result, 'query' => $query, 'canEdit' => $canEdit]) ?>
    </div>
</div>
