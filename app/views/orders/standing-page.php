<?php /** @var array $result  @var array $query  @var bool $canEdit */
$actions = list_search('standing-orders-list', '/orders/standing', $query['q'], 'Search standing orders or customers', '#standing-orders-list-filter-state')
    . list_filter('standing-orders-list', 'state', '/orders/standing', ['active' => 'Running', 'ended' => 'Paused or ended'], $query['state'] ?? '', 'All')
    . ($canEdit ? nav_button('standing-orders-list-add-btn', '/orders/standing/new', 'New Standing Order') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Standing orders', 'screen' => 'standing-orders-list', 'crumbs' => ['Sales' => null, 'Standing orders' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="standing-orders-list-content">
    <div class="row">
        <?= view('orders/partials/standing-table.php', ['result' => $result, 'query' => $query, 'canEdit' => $canEdit]) ?>
    </div>
</div>
