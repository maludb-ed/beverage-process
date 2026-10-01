<?php /** @var array $result  @var array $query  @var bool $canEdit */
$include = '#counts-list-search, #counts-list-filter-status, #counts-list-filter-kind';
$actions = list_search('counts-list', '/counts/', $query['q'], 'Search counts, locations', $include)
    . view('inventory/partials/filter.php', ['screen' => 'counts-list', 'name' => 'status', 'url' => '/counts/', 'options' => COUNT_STATUSES, 'selected' => $query['status'], 'allLabel' => 'All statuses', 'include' => $include])
    . view('inventory/partials/filter.php', ['screen' => 'counts-list', 'name' => 'kind', 'url' => '/counts/', 'options' => COUNT_KINDS, 'selected' => $query['kind'], 'allLabel' => 'All kinds', 'include' => $include])
    . ($canEdit ? nav_button('counts-list-add-btn', '/counts/new', 'Start Count') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Counts', 'screen' => 'counts-list', 'crumbs' => ['Inventory' => null, 'Counts' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="counts-list-content">
    <div class="row">
        <?= view('counts/partials/table.php', ['result' => $result, 'query' => $query]) ?>
    </div>
</div>
