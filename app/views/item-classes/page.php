<?php /** @var array $result  @var array $query  @var bool $canEdit */
$actions = list_search('item-classes-list', '/item-classes/', $query['q'], 'Search item classes', '#item-classes-list-filter-kind')
    . list_filter('item-classes-list', 'kind', '/item-classes/', ITEM_CLASS_KINDS, $query['kind'] ?? '', 'All kinds')
    . ($canEdit ? nav_button('item-classes-list-add-btn', '/item-classes/new' . ($query['kind'] ? '?kind=' . rawurlencode($query['kind']) : ''), $query['kind'] === 'material' ? 'Add Material Type' : 'Add Item Class') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Item classes', 'screen' => 'item-classes-list', 'crumbs' => ['Setup' => null, 'Item classes' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="item-classes-list-content">
    <div class="row">
        <?= view('item-classes/partials/table.php', ['result' => $result, 'query' => $query, 'canEdit' => $canEdit]) ?>
    </div>
</div>
