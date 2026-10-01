<?php /** @var array $result  @var array $query  @var bool $canEdit */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('vessels/partials/row.php', ['vessel' => $row, 'canEdit' => $canEdit]);
}
echo view('shared/list-card.php', [
    'screen' => 'vessels-list', 'title' => 'All vessels', 'url' => '/vessels/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No vessels yet. Add a location first, then the tanks that sit in it.',
    'columns' => [
        ['key' => 'name', 'label' => 'Name', 'sort' => 'name'],
        ['key' => 'kind', 'label' => 'Kind', 'sort' => 'kind'],
        ['key' => 'capacity', 'label' => 'Capacity', 'sort' => 'capacity_l'],
        ['key' => 'status', 'label' => 'Status', 'sort' => 'status'],
        ['key' => 'location', 'label' => 'Location'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
