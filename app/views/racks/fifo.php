<?php /** @var array $groups  @var array $query  @var array $premises  @var array $classes */
$actions = list_search('rack-fifo', '/racks/fifo', $query['q'], 'Product, item, lot or batch', '#rack-fifo-filter-premises-id, #rack-fifo-filter-item-class')
    . (count($premises) > 1 ? list_filter('rack-fifo', 'premises_id', '/racks/fifo', $premises, isset($query['premises_id']) ? (string) $query['premises_id'] : null, 'All premises') : '')
    . list_filter('rack-fifo', 'item_class', '/racks/fifo', $classes, $query['item_class'] ?: null, 'All classes');
?>
<?= view('shared/page-header.php', ['title' => 'FIFO pick order', 'screen' => 'rack-fifo', 'crumbs' => ['Inventory' => null, 'Rack board' => '/racks/', 'FIFO pick order' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="rack-fifo-content">
    <?= view('racks/partials/fifo-list.php', ['groups' => $groups, 'query' => $query]) ?>
</div>
