<?php /** @var array $result  @var array $query  @var array $user */
$actions = list_search('sensory-list', '/sensory/', $query['q'], 'Search batch, lot, panelist')
    . (user_can($user, 'quality') ? nav_button('sensory-list-add-btn', '/sensory/new', 'Record panel') : '');
?>
<?= view('shared/page-header.php', ['title' => 'Sensory panel', 'screen' => 'sensory-list', 'crumbs' => ['Quality' => null, 'Sensory' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="sensory-list-content">
    <div class="row">
        <?= view('sensory/partials/table.php', ['result' => $result, 'query' => $query, 'user' => $user]) ?>
    </div>
</div>
