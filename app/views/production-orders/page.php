<?php /** @var array $result  @var array $query  @var bool $canEdit */
$statusOptions = ['all' => 'All statuses'] + PRODUCTION_ORDER_STATUSES;
$actions = list_search('production-orders-list', '/production-orders/', $query['q'], 'Search orders or products', '#production-orders-list-filter-status')
    . list_filter('production-orders-list', 'status', '/production-orders/', $statusOptions, $query['status'] ?? '', 'Planned, released, in progress')
    . nav_button('production-orders-list-calendar-btn', '/production-orders/calendar', 'Vessel calendar', 'feather-calendar', 'btn btn-light-brand')
    . ($canEdit ? nav_button('production-orders-list-add-btn', '/production-orders/new', 'Add Production Order') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Production orders', 'screen' => 'production-orders-list', 'crumbs' => ['Production' => null, 'Production orders' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="production-orders-list-content">
    <div class="row">
        <?= view('production-orders/partials/table.php', ['result' => $result, 'query' => $query, 'canEdit' => $canEdit]) ?>
    </div>
</div>
