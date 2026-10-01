<?php /** @var array $result  @var array $query  @var int $currentUserId  @var array $errors */
$actions = list_search('users-list', '/users/', $query['q'], 'Search users')
    . nav_button('users-list-add-btn', '/users/new', 'Invite User');
?>
<?= view('shared/page-header.php', ['title' => 'Users', 'screen' => 'users-list', 'crumbs' => ['Setup' => null, 'Users' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="users-list-content">
    <?= view('shared/validation-errors.php', ['errors' => $errors ?? [], 'id' => 'users-list-errors']) ?>
    <div class="row">
        <?= view('users/partials/table.php', ['result' => $result, 'query' => $query, 'currentUserId' => $currentUserId]) ?>
    </div>
</div>
