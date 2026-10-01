<?php /** @var array $result  @var array $query  @var bool $canEdit */
$actions = list_search('transfers-list', '/transfers/', $query['q'], 'Search transfers, locations', '#transfers-list-filter-status')
    . list_filter('transfers-list', 'status', '/transfers/', TRANSFER_STATUSES, $query['status'] ?? '', 'All statuses')
    . ($canEdit ? nav_button('transfers-list-add-btn', '/transfers/new', 'Add Transfer') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Transfers', 'screen' => 'transfers-list', 'crumbs' => ['Inventory' => null, 'Transfers' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="transfers-list-content">
    <div class="row">
        <?= view('transfers/partials/table.php', ['result' => $result, 'query' => $query, 'canEdit' => $canEdit]) ?>
    </div>
</div>
