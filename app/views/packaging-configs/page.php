<?php /** @var array $result  @var array $query  @var bool $canEdit  @var array $products */
$inc = static fn(string $html, string $others) => str_replace('hx-include="#packaging-configs-list-search"', 'hx-include="#packaging-configs-list-search, ' . $others . '"', $html);
$productFilter = $inc(list_filter('packaging-configs-list', 'product_id', '/packaging-configs/', $products, (string) ($query['product_id'] ?? ''), 'All products'), '#packaging-configs-list-filter-package-kind, #packaging-configs-list-filter-active');
$kindFilter = $inc(list_filter('packaging-configs-list', 'package_kind', '/packaging-configs/', PACKAGE_KINDS, $query['package_kind'] ?? '', 'All kinds'), '#packaging-configs-list-filter-product-id, #packaging-configs-list-filter-active');
$activeFilter = $inc(list_filter('packaging-configs-list', 'active', '/packaging-configs/', ['1' => 'Active', '0' => 'Inactive'], $query['active'] ?? '', 'Active and inactive'), '#packaging-configs-list-filter-product-id, #packaging-configs-list-filter-package-kind');
$actions = list_search('packaging-configs-list', '/packaging-configs/', $query['q'], 'Search name, product or item', '#packaging-configs-list-filter-product-id, #packaging-configs-list-filter-package-kind, #packaging-configs-list-filter-active')
    . $productFilter . $kindFilter . $activeFilter
    . ($canEdit ? nav_button('packaging-configs-list-add-btn', '/packaging-configs/new', 'Add Configuration') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Packaging configurations', 'screen' => 'packaging-configs-list', 'crumbs' => ['Products' => null, 'Packaging configurations' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="packaging-configs-list-content">
    <div class="row">
        <?= view('packaging-configs/partials/table.php', ['result' => $result, 'query' => $query, 'canEdit' => $canEdit]) ?>
    </div>
</div>
