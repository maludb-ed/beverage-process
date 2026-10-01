<?php /** @var array $result  @var array $query  @var array $classes  @var bool $canEdit */
$actions = view('inventory/partials/filter.php', ['screen' => 'reorder-list', 'name' => 'item_class', 'url' => '/inventory/reorder', 'options' => $classes, 'selected' => $query['item_class'], 'allLabel' => 'All classes', 'include' => '']);
?>
<?= view('shared/page-header.php', ['title' => 'Reorder', 'screen' => 'reorder-list', 'crumbs' => ['Inventory' => null, 'Reorder' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="reorder-list-content">
    <div class="row">
        <?= view('inventory/partials/reorder-table.php', ['result' => $result, 'query' => $query, 'canEdit' => $canEdit]) ?>
    </div>
</div>
