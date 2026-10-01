<?php /** @var array $result  @var array $query  @var array $premisesOptions  @var bool $canEdit */
$actions = list_search('locations-list', '/locations/', $query['q'], 'Search locations', '#locations-list-filter-premises-id')
    . list_filter('locations-list', 'premises_id', '/locations/', $premisesOptions, isset($query['premises_id']) ? (string) $query['premises_id'] : null, 'All premises')
    . ($canEdit ? nav_button('locations-list-add-btn', '/locations/new', 'Add Location') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Locations', 'screen' => 'locations-list', 'crumbs' => ['Setup' => null, 'Locations' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="locations-list-content">
    <div class="row">
        <?= view('locations/partials/table.php', ['result' => $result, 'query' => $query, 'canEdit' => $canEdit]) ?>
    </div>
</div>
