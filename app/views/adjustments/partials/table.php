<?php /** @var array $result  @var array $query  @var bool $canEdit */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('adjustments/partials/row.php', ['adjustment' => $row, 'canEdit' => $canEdit]);
}
echo view('shared/list-card.php', [
    'screen' => 'adjustments-list', 'title' => 'Inventory adjustments', 'url' => '/adjustments/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No adjustments match.',
    'columns' => [
        ['key' => 'number', 'label' => 'Number', 'sort' => 'number'],
        ['key' => 'location', 'label' => 'Location'],
        ['key' => 'reason', 'label' => 'Reason'],
        ['key' => 'lines', 'label' => 'Lines'],
        ['key' => 'net-qty', 'label' => 'Net quantity'],
        ['key' => 'adjusted', 'label' => 'Adjusted', 'sort' => 'adjusted_at'],
        ['key' => 'status', 'label' => 'Status', 'sort' => 'status'],
        ['key' => 'created-by', 'label' => 'Created by'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
