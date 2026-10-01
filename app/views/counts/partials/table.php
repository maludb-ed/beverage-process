<?php /** @var array $result  @var array $query */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('counts/partials/row.php', ['count' => $row]);
}
echo view('shared/list-card.php', [
    'screen' => 'counts-list', 'title' => 'Inventory counts', 'url' => '/counts/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No counts match.',
    'columns' => [
        ['key' => 'number', 'label' => 'Number', 'sort' => 'number'],
        ['key' => 'location', 'label' => 'Location'],
        ['key' => 'kind', 'label' => 'Kind'],
        ['key' => 'lines', 'label' => 'Lines'],
        ['key' => 'variance-lines', 'label' => 'Variance lines'],
        ['key' => 'started', 'label' => 'Started', 'sort' => 'started_at'],
        ['key' => 'status', 'label' => 'Status', 'sort' => 'status'],
        ['key' => 'approved-by', 'label' => 'Approved by'],
    ],
]);
