<?php /** @var array $result  @var array $query  @var array $classes  @var array $locations  @var bool $canEdit  @var string $kind */
$meta = INVENTORY_KINDS[$kind];
$screen = $meta['screen'];
$url = '/inventory/' . $kind;
// Finished product is one item class, so that screen has no class filter.
$include = '#' . $screen . '-search, #' . $screen . '-filter-location-id' . (count($classes) > 1 ? ', #' . $screen . '-filter-item-class' : '');
$actions = list_search($screen, $url, $query['q'], 'Search items, lots', $include)
    . (count($classes) > 1 ? view('inventory/partials/filter.php', ['screen' => $screen, 'name' => 'item_class', 'url' => $url, 'options' => $classes, 'selected' => $query['item_class'], 'allLabel' => 'All classes', 'include' => $include]) : '')
    . view('inventory/partials/filter.php', ['screen' => $screen, 'name' => 'location_id', 'url' => $url, 'options' => $locations, 'selected' => (string) ($query['location_id'] ?? ''), 'allLabel' => 'All locations', 'include' => $include])
    . ($canEdit && $kind === 'materials' ? nav_button($screen . '-add-item-btn', '/items/new?kind=material', 'Add Material', 'feather-plus', 'btn btn-light-brand')
        . nav_button($screen . '-classes-btn', '/item-classes/?kind=material', 'Material types', 'feather-tag', 'btn btn-light-brand') : '')
    . ($canEdit ? nav_button($screen . '-transfer-btn', '/transfers/new', 'New Transfer', 'feather-repeat', 'btn btn-light-brand')
        . nav_button($screen . '-adjustment-btn', '/adjustments/new', 'New Adjustment', 'feather-sliders', 'btn btn-light-brand')
        . nav_button($screen . '-count-btn', '/counts/new', 'Start Count', 'feather-clipboard') : '');
?>
<?= view('shared/page-header.php', ['title' => $meta['title'], 'screen' => $screen, 'crumbs' => ['Inventory' => null, $meta['crumb'] => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="<?= e($screen) ?>-content">
    <div class="row">
        <?= view('inventory/partials/table.php', ['result' => $result, 'query' => $query, 'kind' => $kind]) ?>
    </div>
</div>
