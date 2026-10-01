<?php /** @var array $result  @var array $query */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('mcp-tokens/partials/row.php', ['token' => $row]);
}
echo view('shared/list-card.php', [
    'screen' => 'settings-mcp-tokens', 'title' => 'Tokens', 'url' => '/settings/mcp-tokens', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No tokens yet. Create one to connect an AI tool.',
    'columns' => [
        ['key' => 'name', 'label' => 'Name', 'sort' => 'name'],
        ['key' => 'scope', 'label' => 'Can read', 'sort' => 'scope'],
        ['key' => 'prefix', 'label' => 'Starts with'],
        ['key' => 'created-at', 'label' => 'Created', 'sort' => 'created_at'],
        ['key' => 'last-used-at', 'label' => 'Last used', 'sort' => 'last_used_at'],
        ['key' => 'status', 'label' => 'Status'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
