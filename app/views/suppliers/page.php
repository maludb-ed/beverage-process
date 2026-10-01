<?php /** @var array $result  @var array $query  @var bool $canEdit */
$actions = list_search('suppliers-list', '/suppliers/', $query['q'], 'Search suppliers')
    . ($canEdit ? nav_button('suppliers-list-add-btn', '/suppliers/new', 'Add Supplier') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Suppliers', 'screen' => 'suppliers-list', 'crumbs' => ['Setup' => null, 'Suppliers' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="suppliers-list-content">
    <div class="row">
        <?= view('suppliers/partials/table.php', ['result' => $result, 'query' => $query, 'canEdit' => $canEdit]) ?>
    </div>
</div>
