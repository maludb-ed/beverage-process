<?php /** @var array $result  @var array $query  @var bool $canEdit  @var bool $canPrice */
$actions = list_search('orders-list', '/orders/', $query['q'], 'Search orders, customers or references', '#orders-list-filter-status')
    . list_filter('orders-list', 'status', '/orders/', ORDER_STATUS_FILTERS, $query['status'] ?? '', 'All statuses')
    . ($canEdit ? nav_button('orders-list-add-btn', '/orders/new', 'New Order') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Customer orders', 'screen' => 'orders-list', 'crumbs' => ['Sales' => null, 'Customer orders' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="orders-list-content">
    <div class="row">
        <?= view('orders/partials/table.php', ['result' => $result, 'query' => $query, 'canEdit' => $canEdit, 'canPrice' => $canPrice]) ?>
    </div>
</div>
