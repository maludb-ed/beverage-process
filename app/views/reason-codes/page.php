<?php /** @var array $result  @var array $query  @var bool $canEdit */
$actions = list_search('reason-codes-list', '/reason-codes/', $query['q'], 'Search reason codes', '#reason-codes-list-filter-applies-to')
    . list_filter('reason-codes-list', 'applies_to', '/reason-codes/', REASON_APPLIES_TO, $query['applies_to'] ?? '', 'All types')
    . ($canEdit ? nav_button('reason-codes-list-add-btn', '/reason-codes/new', 'Add Reason Code') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Reason codes', 'screen' => 'reason-codes-list', 'crumbs' => ['Setup' => null, 'Reason codes' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="reason-codes-list-content">
    <div class="row">
        <?= view('reason-codes/partials/table.php', ['result' => $result, 'query' => $query, 'canEdit' => $canEdit]) ?>
    </div>
</div>
