<?php /** @var array $result  @var array $query  @var array $classes  @var array $locations  @var bool $canEdit */
$include = '#inventory-list-search, #inventory-list-filter-item-class, #inventory-list-filter-location-id';
$actions = list_search('inventory-list', '/inventory/', $query['q'], 'Search items, lots', $include)
    . view('inventory/partials/filter.php', ['screen' => 'inventory-list', 'name' => 'item_class', 'url' => '/inventory/', 'options' => $classes, 'selected' => $query['item_class'], 'allLabel' => 'All classes', 'include' => $include])
    . view('inventory/partials/filter.php', ['screen' => 'inventory-list', 'name' => 'location_id', 'url' => '/inventory/', 'options' => $locations, 'selected' => (string) ($query['location_id'] ?? ''), 'allLabel' => 'All locations', 'include' => $include])
    . ($canEdit ? nav_button('inventory-list-transfer-btn', '/transfers/new', 'New Transfer', 'feather-repeat', 'btn btn-light-brand')
        . nav_button('inventory-list-adjustment-btn', '/adjustments/new', 'New Adjustment', 'feather-sliders', 'btn btn-light-brand')
        . nav_button('inventory-list-count-btn', '/counts/new', 'Start Count', 'feather-clipboard') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Inventory', 'screen' => 'inventory-list', 'crumbs' => ['Inventory' => null, 'On hand' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="inventory-list-content">
    <div class="row">
        <?= view('inventory/partials/table.php', ['result' => $result, 'query' => $query]) ?>
    </div>
</div>
