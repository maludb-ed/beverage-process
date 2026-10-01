<?php /** @var array $result  @var array $query  @var array $user */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('sensory/partials/row.php', ['record' => $row, 'user' => $user]);
}
echo view('shared/list-card.php', [
    'screen' => 'sensory-list', 'title' => 'Panel records', 'url' => '/sensory/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No panel records yet. Record the first tasting.',
    'columns' => [
        ['key' => 'panel-on', 'label' => 'Panel date', 'sort' => 'panel_on'],
        ['key' => 'target', 'label' => 'Batch / lot'],
        ['key' => 'panelist', 'label' => 'Panelist'],
        ['key' => 'sample', 'label' => 'Sample'],
        ['key' => 'verdict', 'label' => 'Verdict', 'sort' => 'verdict'],
        ['key' => 'faults', 'label' => 'Faults'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
