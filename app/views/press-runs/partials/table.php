<?php /** @var array $result  @var array $query  @var bool $canEdit */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('press-runs/partials/row.php', ['run' => $row, 'canEdit' => $canEdit]);
}
echo view('shared/list-card.php', [
    'screen' => 'press-runs-list', 'title' => 'Press runs', 'url' => '/press-runs/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No press runs match.',
    'columns' => [
        ['key' => 'number', 'label' => 'Number', 'sort' => 'number'],
        ['key' => 'run-on', 'label' => 'Date', 'sort' => 'run_on'],
        ['key' => 'press', 'label' => 'Press'],
        ['key' => 'fruit', 'label' => 'Fruit'],
        ['key' => 'juice', 'label' => 'Juice'],
        ['key' => 'yield', 'label' => 'Yield'],
        ['key' => 'status', 'label' => 'Status', 'sort' => 'status'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
