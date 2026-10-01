<?php /** @var array $result  @var array $query  @var array $reasons  @var bool $canEdit */
$include = '#adjustments-list-search, #adjustments-list-filter-status, #adjustments-list-filter-reason-code-id';
$actions = list_search('adjustments-list', '/adjustments/', $query['q'], 'Search adjustments, locations, reasons', $include)
    . view('inventory/partials/filter.php', ['screen' => 'adjustments-list', 'name' => 'status', 'url' => '/adjustments/', 'options' => ADJUSTMENT_STATUSES, 'selected' => $query['status'], 'allLabel' => 'All statuses', 'include' => $include])
    . view('inventory/partials/filter.php', ['screen' => 'adjustments-list', 'name' => 'reason_code_id', 'url' => '/adjustments/', 'options' => $reasons, 'selected' => (string) ($query['reason_code_id'] ?? ''), 'allLabel' => 'All reasons', 'include' => $include])
    . ($canEdit ? nav_button('adjustments-list-add-btn', '/adjustments/new', 'Add Adjustment') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Adjustments', 'screen' => 'adjustments-list', 'crumbs' => ['Inventory' => null, 'Adjustments' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="adjustments-list-content">
    <div class="row">
        <?= view('adjustments/partials/table.php', ['result' => $result, 'query' => $query, 'canEdit' => $canEdit]) ?>
    </div>
</div>
