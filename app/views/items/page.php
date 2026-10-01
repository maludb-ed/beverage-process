<?php /** @var array $result  @var array $query  @var bool $canEdit */
$actions = list_search('items-list', '/items/', $query['q'], 'Search items', '#items-list-filter-item-class')
    . list_filter('items-list', 'item_class', '/items/', ITEM_CLASSES, $query['item_class'] ?? '', 'All classes')
    . ($canEdit ? nav_button('items-list-add-btn', '/items/new', 'Add Item') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Items', 'screen' => 'items-list', 'crumbs' => ['Setup' => null, 'Items' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="items-list-content">
    <div class="row">
        <?= view('items/partials/table.php', ['result' => $result, 'query' => $query, 'canEdit' => $canEdit]) ?>
    </div>
</div>
