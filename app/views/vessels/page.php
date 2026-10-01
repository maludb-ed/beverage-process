<?php /** @var array $result  @var array $query  @var bool $canEdit */
$actions = list_search('vessels-list', '/vessels/', $query['q'], 'Search vessels')
    . ($canEdit ? nav_button('vessels-list-add-btn', '/vessels/new', 'Add Vessel') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Vessels', 'screen' => 'vessels-list', 'crumbs' => ['Setup' => null, 'Vessels' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="vessels-list-content">
    <div class="row">
        <?= view('vessels/partials/table.php', ['result' => $result, 'query' => $query, 'canEdit' => $canEdit]) ?>
    </div>
</div>
