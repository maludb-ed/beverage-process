<?php /** @var array $result  @var array $query  @var array $products  @var array $locations */
$actions = list_search('finished-lots-list', '/finished-lots/', $query['q'], 'Search lots, products, batches', '#finished-lots-list-filter-product-id, #finished-lots-list-filter-package-kind, #finished-lots-list-filter-location-id')
    . list_filter('finished-lots-list', 'product_id', '/finished-lots/', $products, $query['product_id'] ?? '', 'All products')
    . list_filter('finished-lots-list', 'package_kind', '/finished-lots/', FINISHED_LOT_PACKAGE_KINDS, $query['package_kind'] ?? '', 'All packages')
    . list_filter('finished-lots-list', 'location_id', '/finished-lots/', $locations, $query['location_id'] ?? '', 'All locations');
?>
<?= view('shared/page-header.php', ['title' => 'Finished goods', 'screen' => 'finished-lots-list', 'crumbs' => ['Packaging' => null, 'Finished goods' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="finished-lots-list-content">
    <div class="row">
        <?= view('finished-lots/partials/table.php', ['result' => $result, 'query' => $query]) ?>
    </div>
</div>
