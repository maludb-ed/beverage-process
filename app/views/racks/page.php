<?php /** @var array $board  @var array $query  @var array $premises  @var array $areas  @var bool $canEdit */
$actions = list_search('rack-board', '/racks/', $query['q'], 'Product, lot, batch or rack', '#rack-board-filter-premises-id, #rack-board-filter-area-id')
    . (count($premises) > 1 ? list_filter('rack-board', 'premises_id', '/racks/', $premises, isset($query['premises_id']) ? (string) $query['premises_id'] : null, 'All premises') : '')
    . list_filter('rack-board', 'area_id', '/racks/', $areas, isset($query['area_id']) ? (string) $query['area_id'] : null, 'All areas')
    . nav_button('rack-board-fifo-btn', '/racks/fifo', 'FIFO pick order', 'feather-list', 'btn btn-light-brand')
    . ($canEdit ? nav_button('rack-board-add-btn', '/racks/new', 'Add Rack') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Rack board', 'screen' => 'rack-board', 'crumbs' => ['Inventory' => null, 'Rack board' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="rack-board-content">
    <?= view('racks/partials/board.php', ['board' => $board, 'query' => $query, 'canEdit' => $canEdit]) ?>
</div>
