<?php /** @var array $result  @var array $query  @var bool $canEdit */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('removals/partials/row.php', ['removal' => $row, 'canEdit' => $canEdit]);
}
echo view('shared/list-card.php', [
    'screen' => 'removals-list', 'title' => 'Removals and returns', 'url' => '/removals/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No removals match. Finished goods leave bond through a removal.',
    'columns' => [
        ['key' => 'number', 'label' => 'Number', 'sort' => 'number'],
        ['key' => 'removed-at', 'label' => 'Date', 'sort' => 'removed_at'],
        ['key' => 'direction', 'label' => 'Dir.'],
        ['key' => 'destination', 'label' => 'Destination', 'sort' => 'destination_kind'],
        ['key' => 'units', 'label' => 'Units'],
        ['key' => 'gallons', 'label' => 'Gallons'],
        ['key' => 'tax', 'label' => 'Tax'],
        ['key' => 'status', 'label' => 'Status', 'sort' => 'status'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
