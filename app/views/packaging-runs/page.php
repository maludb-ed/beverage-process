<?php /** @var array $result  @var array $query  @var bool $canEdit */
$actions = list_search('packaging-runs-list', '/packaging-runs/', $query['q'], 'Search runs, batches, products', '#packaging-runs-list-filter-status')
    . list_filter('packaging-runs-list', 'status', '/packaging-runs/', PACKAGING_RUN_STATUSES, $query['status'] ?? '', 'All except cancelled')
    . ($canEdit ? nav_button('packaging-runs-list-add-btn', '/packaging-runs/new', 'Add Packaging Run') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Packaging runs', 'screen' => 'packaging-runs-list', 'crumbs' => ['Packaging' => null, 'Packaging runs' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="packaging-runs-list-content">
    <div class="row">
        <?= view('packaging-runs/partials/table.php', ['result' => $result, 'query' => $query, 'canEdit' => $canEdit]) ?>
    </div>
</div>
