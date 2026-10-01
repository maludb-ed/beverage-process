<?php /** @var array $result  @var array $query  @var bool $canEdit */
$actions = list_search('premises-list', '/premises/', $query['q'], 'Search premises')
    . ($canEdit ? nav_button('premises-list-add-btn', '/premises/new', 'Add Premises') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Premises', 'screen' => 'premises-list', 'crumbs' => ['Setup' => null, 'Premises' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="premises-list-content">
    <div class="row">
        <?= view('premises/partials/table.php', ['result' => $result, 'query' => $query, 'canEdit' => $canEdit]) ?>
    </div>
</div>
