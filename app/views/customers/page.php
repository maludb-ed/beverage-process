<?php /** @var array $result  @var array $query  @var bool $canEdit */
$actions = list_search('customers-list', '/customers/', $query['q'], 'Search name, contact, email')
    . ($canEdit ? nav_button('customers-list-add-btn', '/customers/new', 'Add Customer') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Customers', 'screen' => 'customers-list', 'crumbs' => ['Compliance' => null, 'Customers' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="customers-list-content">
    <div class="row">
        <?= view('customers/partials/table.php', ['result' => $result, 'query' => $query, 'canEdit' => $canEdit]) ?>
    </div>
</div>
