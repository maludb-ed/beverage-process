<?php /** @var array $result  @var array $query  @var bool $canEdit */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('suppliers/partials/row.php', ['supplier' => $row, 'canEdit' => $canEdit]);
}
echo view('shared/list-card.php', [
    'screen' => 'suppliers-list', 'title' => 'All suppliers', 'url' => '/suppliers/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No suppliers yet. Add the first supplier.',
    'columns' => [
        ['key' => 'name', 'label' => 'Name', 'sort' => 'name'],
        ['key' => 'kind', 'label' => 'Kind', 'sort' => 'kind'],
        ['key' => 'contact-name', 'label' => 'Contact'],
        ['key' => 'email', 'label' => 'Email'],
        ['key' => 'phone', 'label' => 'Phone'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
