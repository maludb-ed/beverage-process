<?php /** @var array $vessels  @var array $query  @var array $premises */
$actions = (count($premises) > 1 ? list_filter('tank-board', 'premises_id', '/tank-board/', $premises, $query['premises_id'] ?? '', 'All premises') : '')
    . list_filter('tank-board', 'kind', '/tank-board/', TANK_BOARD_KINDS, $query['kind'] ?? '', 'All kinds');
// list_filter targets #tank-board-results, the region around #tank-board-grid.
?>
<?= view('shared/page-header.php', ['title' => 'Tank board', 'screen' => 'tank-board', 'crumbs' => ['Production' => null, 'Tank board' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="tank-board-content">
    <?= view('tank-board/partials/grid.php', ['vessels' => $vessels, 'query' => $query]) ?>
</div>
