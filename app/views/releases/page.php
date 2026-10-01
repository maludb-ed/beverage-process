<?php /** @var array $batches  @var array $lots  @var array $query  @var array $user */
$actions = list_search('release-queue', '/releases/', $query['q'], 'Search batch, product, lot');
?>
<?= view('shared/page-header.php', ['title' => 'Release queue', 'screen' => 'release-queue', 'crumbs' => ['Quality' => null, 'Release queue' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="release-queue-content">
    <div class="row">
        <?= view('releases/partials/queue.php', ['batches' => $batches, 'lots' => $lots, 'query' => $query, 'user' => $user]) ?>
    </div>
</div>
