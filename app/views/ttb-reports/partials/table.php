<?php /** @var array $result  @var array $query  @var bool $canEdit */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('ttb-reports/partials/row.php', ['report' => $row]);
}
echo view('shared/list-card.php', [
    'screen' => 'ttb-reports-list', 'title' => 'Period reports', 'url' => '/ttb-reports/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No reports yet. Generate the 5120.17 for a period.',
    'columns' => [
        ['key' => 'number', 'label' => 'Number'],
        ['key' => 'premises', 'label' => 'Premises'],
        ['key' => 'form', 'label' => 'Form'],
        ['key' => 'period', 'label' => 'Period', 'sort' => 'period_start'],
        ['key' => 'status', 'label' => 'Status', 'sort' => 'status'],
        ['key' => 'generated', 'label' => 'Generated'],
        ['key' => 'filed', 'label' => 'Filed'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
