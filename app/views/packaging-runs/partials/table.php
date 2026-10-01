<?php /** @var array $result  @var array $query  @var bool $canEdit */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('packaging-runs/partials/row.php', ['run' => $row, 'canEdit' => $canEdit]);
}
echo view('shared/list-card.php', [
    'screen' => 'packaging-runs-list', 'title' => 'Packaging runs', 'url' => '/packaging-runs/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No packaging runs match.',
    'columns' => [
        ['key' => 'number', 'label' => 'Number', 'sort' => 'number'],
        ['key' => 'batch', 'label' => 'Batch'],
        ['key' => 'package', 'label' => 'Package'],
        ['key' => 'run-on', 'label' => 'Run date', 'sort' => 'run_on'],
        ['key' => 'volume-in', 'label' => 'Volume in'],
        ['key' => 'units-out', 'label' => 'Units out'],
        ['key' => 'loss', 'label' => 'Loss'],
        ['key' => 'status', 'label' => 'Status', 'sort' => 'status'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
