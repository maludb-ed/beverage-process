<?php /** @var array $result  @var array $query  @var array $user  @var array $types */
$typeOptions = array_column($types, 'name', 'code');
$include = '#lab-list-filter-spec-result, #lab-list-filter-measurement-type-code, #lab-list-filter-days';
$actions = list_search('lab-list', '/lab/', $query['q'], 'Search batch, lot, measurement', $include)
    . list_filter('lab-list', 'spec_result', '/lab/', ['fail' => 'Out of spec only'], $query['spec_result'], 'All results')
    . list_filter('lab-list', 'measurement_type_code', '/lab/', $typeOptions, $query['measurement_type_code'], 'All measurements')
    . list_filter('lab-list', 'days', '/lab/', READING_DAY_OPTIONS, $query['days'], 'Last 30 days')
    . (user_can($user, 'quality') ? nav_button('lab-list-add-btn', '/lab/new', 'Record reading') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Lab readings', 'screen' => 'lab-list', 'crumbs' => ['Quality' => null, 'Lab' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="lab-list-content">
    <div class="row">
        <?= view('lab/partials/table.php', ['result' => $result, 'query' => $query, 'user' => $user, 'types' => $types]) ?>
    </div>
</div>
