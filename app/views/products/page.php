<?php /** @var array $result  @var array $query  @var bool $canEdit */
$statusFilter = list_filter('products-list', 'status', '/products/', PRODUCT_STATUSES, $query['status'] ?? '', 'All statuses');
$beverageFilter = list_filter('products-list', 'beverage_type', '/products/', PRODUCT_BEVERAGES, $query['beverage_type'] ?? '', 'All beverages');
$statusFilter = str_replace('hx-include="#products-list-search"', 'hx-include="#products-list-search, #products-list-filter-beverage-type"', $statusFilter);
$beverageFilter = str_replace('hx-include="#products-list-search"', 'hx-include="#products-list-search, #products-list-filter-status"', $beverageFilter);
$actions = list_search('products-list', '/products/', $query['q'], 'Search name, code or style', '#products-list-filter-status, #products-list-filter-beverage-type')
    . $statusFilter . $beverageFilter
    . ($canEdit ? nav_button('products-list-add-btn', '/products/new', 'Add Product') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Products', 'screen' => 'products-list', 'crumbs' => ['Products' => null, 'Products and recipes' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="products-list-content">
    <div class="row">
        <?= view('products/partials/table.php', ['result' => $result, 'query' => $query, 'canEdit' => $canEdit]) ?>
    </div>
</div>
