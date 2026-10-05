<?php /** @var array $vessels  @var array $query  @var array $premises  @var bool $canArrange */
$actions = (count($premises) > 1 ? list_filter('tank-view', 'premises_id', '/tanks/', $premises, $query['premises_id'] ?? '', 'All premises') : '')
    . list_filter('tank-view', 'kind', '/tanks/', TANK_BOARD_KINDS, $query['kind'] ?? '', 'All kinds')
    . nav_button('tank-view-board-btn', '/tank-board/', 'Tank board', 'feather-list', 'btn btn-light-brand')
    . ($canArrange ? nav_button('tank-view-add-btn', '/vessels/new', 'Add Vessel') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Tank view', 'screen' => 'tank-view', 'crumbs' => ['Inventory' => null, 'Tank view' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="tank-view-content">
    <?= view('tanks/partials/board.php', ['vessels' => $vessels, 'query' => $query, 'canArrange' => $canArrange]) ?>
</div>
