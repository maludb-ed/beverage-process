<?php /** @var array $result  @var array $query  @var bool $canEdit */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('customers/partials/row.php', ['customer' => $row, 'canEdit' => $canEdit]);
}
echo view('shared/list-card.php', [
    'screen' => 'customers-list', 'title' => 'All customers', 'url' => '/customers/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No customers match. Add the distributors, retailers and consignees you ship to.',
    'columns' => [
        ['key' => 'name', 'label' => 'Name', 'sort' => 'name'],
        ['key' => 'kind', 'label' => 'Kind', 'sort' => 'kind'],
        ['key' => 'default-destination', 'label' => 'Default destination'],
        ['key' => 'kegs-out', 'label' => 'Kegs out'],
        ['key' => 'last-removal', 'label' => 'Last removal'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
