<?php /** @var array $result  @var array $query */
$actions = list_search('lots-list', '/lots/', $query['q'], 'Search lots, supplier lots, items', '#lots-list-filter-quality-status, #lots-list-filter-item-class, #lots-list-filter-expiring')
    . list_filter('lots-list', 'quality_status', '/lots/', LOT_STATUSES, $query['quality_status'] ?? '', 'All statuses')
    . list_filter('lots-list', 'item_class', '/lots/', lot_item_class_options(), $query['item_class'] ?? '', 'All classes')
    . list_filter('lots-list', 'expiring', '/lots/', ['1' => 'Expiring within 30 days'], $query['expiring'] ?? '', 'Any expiry');
?>
<?= view('shared/page-header.php', ['title' => 'Lots', 'screen' => 'lots-list', 'crumbs' => ['Receiving' => null, 'Lots' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="lots-list-content">
    <div class="row">
        <?= view('lots/partials/table.php', ['result' => $result, 'query' => $query]) ?>
    </div>
</div>
