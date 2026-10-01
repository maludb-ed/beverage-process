<?php /** @var array $result  @var array $query  @var int $currentUserId */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('users/partials/row.php', ['target' => $row, 'currentUserId' => $currentUserId]);
}
echo view('shared/list-card.php', [
    'screen' => 'users-list', 'title' => 'All users', 'url' => '/users/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No users match.',
    'columns' => [
        ['key' => 'display-name', 'label' => 'Name', 'sort' => 'display_name'],
        ['key' => 'email', 'label' => 'Email', 'sort' => 'email'],
        ['key' => 'role', 'label' => 'Role', 'sort' => 'role'],
        ['key' => 'status', 'label' => 'Status', 'sort' => 'status'],
        ['key' => 'last-login-at', 'label' => 'Last sign-in', 'sort' => 'last_login_at'],
        ['key' => 'two-factor', 'label' => '2FA'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
