<?php /** @var array $result  @var array $query  @var array $classes  @var bool $canEdit  @var array $overheadRates */
$inc = static fn(string $html, string $others) => str_replace('hx-include="#standard-costs-list-search"', 'hx-include="#standard-costs-list-search, ' . $others . '"', $html);
$classFilter = $inc(list_filter('standard-costs-list', 'item_class', '/standard-costs/', $classes, $query['item_class'] ?? '', 'All classes'), '#standard-costs-list-filter-costing-method');
$methodFilter = $inc(list_filter('standard-costs-list', 'costing_method', '/standard-costs/', STANDARD_COST_METHODS, $query['costing_method'] ?? '', 'All costing methods'), '#standard-costs-list-filter-item-class');
$actions = list_search('standard-costs-list', '/standard-costs/', $query['q'], 'Search item name or code', '#standard-costs-list-filter-item-class, #standard-costs-list-filter-costing-method')
    . $classFilter . $methodFilter
    . ($canEdit ? nav_button('standard-costs-list-add-btn', '/standard-costs/new', 'Set Standard Cost') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Standard costs', 'screen' => 'standard-costs-list', 'crumbs' => ['Products' => null, 'Standard costs' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="standard-costs-list-content">
    <div class="row">
        <?= view('standard-costs/partials/overhead-card.php', ['overheadRates' => $overheadRates, 'canEdit' => $canEdit]) ?>
    </div>
    <div class="row">
        <?= view('standard-costs/partials/table.php', ['result' => $result, 'query' => $query, 'classes' => $classes, 'canEdit' => $canEdit]) ?>
    </div>
</div>
