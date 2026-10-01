<?php /** @var array $result  @var array $query  @var bool $canEdit */
$actions = list_search('ttb-reports-list', '/ttb-reports/', $query['q'], 'Search report number')
    . ($canEdit ? nav_button('ttb-reports-list-add-btn', '/ttb-reports/new', 'Generate report', 'feather-file-plus') : '');
?>
<?= view('shared/page-header.php', ['title' => 'TTB reports', 'screen' => 'ttb-reports-list', 'crumbs' => ['Compliance' => null, 'TTB reports' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="ttb-reports-list-content">
    <div class="row">
        <?= view('ttb-reports/partials/table.php', ['result' => $result, 'query' => $query, 'canEdit' => $canEdit]) ?>
    </div>
</div>
