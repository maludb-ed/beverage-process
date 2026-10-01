<?php /** @var array $result  @var array $query  @var bool $canEdit */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('premises/partials/row.php', ['premises' => $row, 'canEdit' => $canEdit]);
}
echo view('shared/list-card.php', [
    'screen' => 'premises-list', 'title' => 'All premises', 'url' => '/premises/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No premises yet. Add the bonded winery permit first.',
    'columns' => [
        ['key' => 'name', 'label' => 'Name', 'sort' => 'name'],
        ['key' => 'kind', 'label' => 'Kind', 'sort' => 'kind'],
        ['key' => 'registry-number', 'label' => 'Registry number'],
        ['key' => 'report-form', 'label' => 'Report form'],
        ['key' => 'filing-frequency', 'label' => 'Filing'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
