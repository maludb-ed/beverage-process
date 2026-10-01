<?php /** @var array $result  @var array $query  @var bool $canEdit */
$actions = list_search('receipts-list', '/receipts/', $query['q'], 'Search receipts, suppliers, delivery notes', '#receipts-list-filter-status')
    . list_filter('receipts-list', 'status', '/receipts/', RECEIPT_STATUSES, $query['status'] ?? '', 'All statuses')
    . ($canEdit ? nav_button('receipts-list-add-btn', '/receipts/new', 'Add Receipt') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Receipts', 'screen' => 'receipts-list', 'crumbs' => ['Receiving' => null, 'Receipts' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="receipts-list-content">
    <div class="row">
        <?= view('receipts/partials/table.php', ['result' => $result, 'query' => $query, 'canEdit' => $canEdit]) ?>
    </div>
</div>
