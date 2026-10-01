<?php /** @var array $result  @var array $query  @var bool $canEdit */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('reason-codes/partials/row.php', ['reason' => $row, 'canEdit' => $canEdit]);
}
echo view('shared/list-card.php', [
    'screen' => 'reason-codes-list', 'title' => 'All reason codes', 'url' => '/reason-codes/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No reason codes match.',
    'columns' => [
        ['key' => 'code', 'label' => 'Code', 'sort' => 'code'],
        ['key' => 'name', 'label' => 'Name'],
        ['key' => 'applies-to', 'label' => 'Applies to', 'sort' => 'applies_to'],
        ['key' => 'ttb-category', 'label' => 'TTB category', 'sort' => 'ttb_category'],
        ['key' => 'classification', 'label' => 'Classification'],
        ['key' => 'requires-approval-above', 'label' => 'Approval above'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
