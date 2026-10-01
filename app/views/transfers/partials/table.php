<?php /** @var array $result  @var array $query  @var bool $canEdit */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('transfers/partials/row.php', ['transfer' => $row, 'canEdit' => $canEdit]);
}
echo view('shared/list-card.php', [
    'screen' => 'transfers-list', 'title' => 'Stock transfers', 'url' => '/transfers/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No transfers match.',
    'columns' => [
        ['key' => 'number', 'label' => 'Number', 'sort' => 'number'],
        ['key' => 'from', 'label' => 'From'],
        ['key' => 'to', 'label' => 'To'],
        ['key' => 'lines', 'label' => 'Lines'],
        ['key' => 'transferred', 'label' => 'Transferred', 'sort' => 'transferred_at'],
        ['key' => 'status', 'label' => 'Status', 'sort' => 'status'],
        ['key' => 'posted-by', 'label' => 'Posted by'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
