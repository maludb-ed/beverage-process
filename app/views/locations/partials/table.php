<?php /** @var array $result  @var array $query  @var bool $canEdit */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('locations/partials/row.php', ['location' => $row, 'canEdit' => $canEdit]);
}
echo view('shared/list-card.php', [
    'screen' => 'locations-list', 'title' => 'All locations', 'url' => '/locations/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No locations yet. Add a receiving dock and a cellar first.',
    'columns' => [
        ['key' => 'name', 'label' => 'Name', 'sort' => 'name'],
        ['key' => 'premises', 'label' => 'Premises'],
        ['key' => 'kind', 'label' => 'Kind', 'sort' => 'kind'],
        ['key' => 'tax-state', 'label' => 'Tax state', 'sort' => 'tax_state'],
        ['key' => 'racks', 'label' => 'Racks'],
        ['key' => 'allow-negative', 'label' => 'Allow negative'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
