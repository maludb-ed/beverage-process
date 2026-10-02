<?php /** @var array $result  @var array $query  @var bool $canEdit */
$actions = list_search('purchase-orders-list', '/purchase-orders/', $query['q'], 'Search orders or suppliers', '#purchase-orders-list-filter-status')
    . list_filter('purchase-orders-list', 'status', '/purchase-orders/', PO_STATUSES, $query['status'] ?? '', 'All statuses')
    . ($canEdit ? nav_button('purchase-orders-list-add-btn', '/purchase-orders/new', 'Add Purchase Order') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Purchase orders', 'screen' => 'purchase-orders-list', 'crumbs' => ['Purchasing' => null, 'Purchase Orders' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="purchase-orders-list-content">
    <div class="row">
        <?= view('purchase-orders/partials/table.php', ['result' => $result, 'query' => $query, 'canEdit' => $canEdit]) ?>
    </div>
</div>
