<?php /** @var array $result  @var array $query  @var array $user  @var array $types */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('lab/partials/row.php', ['reading' => $row, 'user' => $user]);
}
echo view('shared/list-card.php', [
    'screen' => 'lab-list', 'title' => 'Readings', 'url' => '/lab/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No readings match. Record a lab reading to start the curve.',
    'columns' => [
        ['key' => 'taken', 'label' => 'Taken', 'sort' => 'taken_at'],
        ['key' => 'target', 'label' => 'Batch / lot'],
        ['key' => 'measurement', 'label' => 'Measurement', 'sort' => 'measurement_type_code'],
        ['key' => 'value', 'label' => 'Value'],
        ['key' => 'stage', 'label' => 'Stage'],
        ['key' => 'spec', 'label' => 'Spec', 'sort' => 'spec_result'],
        ['key' => 'lab', 'label' => 'Lab'],
        ['key' => 'analyst', 'label' => 'Analyst'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
