<?php /** @var array $result  @var array $query  @var bool $canEdit */
$actions = list_search('press-runs-list', '/press-runs/', $query['q'], 'Search press runs', '#press-runs-list-filter-status')
    . list_filter('press-runs-list', 'status', '/press-runs/', PRESS_RUN_STATUSES, $query['status'] ?? '', 'All statuses')
    . ($canEdit ? nav_button('press-runs-list-add-btn', '/press-runs/new', 'Add Press Run') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Press runs', 'screen' => 'press-runs-list', 'crumbs' => ['Production' => null, 'Press runs' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="press-runs-list-content">
    <div class="row">
        <?= view('press-runs/partials/table.php', ['result' => $result, 'query' => $query, 'canEdit' => $canEdit]) ?>
    </div>
</div>
