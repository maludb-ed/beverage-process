<?php /** @var array $result  @var array $query  @var bool $canEdit */
$statusOptions = ['all' => 'All statuses'] + BATCH_STATUSES;
unset($statusOptions['active']);
$actions = list_search('batches-list', '/batches/', $query['q'], 'Search batches, products', '#batches-list-filter-status')
    . list_filter('batches-list', 'status', '/batches/', $statusOptions, $query['status'] ?? '', 'Active')
    . ($canEdit ? nav_button('batches-list-blend-btn', '/batches/blend', 'Blend', 'feather-git-merge', 'btn btn-light-brand') . nav_button('batches-list-add-btn', '/batches/new', 'Pitch a Batch') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Batches', 'screen' => 'batches-list', 'crumbs' => ['Production' => null, 'Batches' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="batches-list-content">
    <div class="row">
        <?= view('batches/partials/table.php', ['result' => $result, 'query' => $query, 'canEdit' => $canEdit]) ?>
    </div>
</div>
