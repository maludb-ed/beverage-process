<?php /** @var array $result  @var array $query  @var bool $canEdit */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('approvals/partials/row.php', ['approval' => $row, 'canEdit' => $canEdit]);
}
echo view('shared/list-card.php', [
    'screen' => 'approvals-list', 'title' => 'Approvals', 'url' => '/approvals/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No approvals match.',
    'columns' => [
        ['key' => 'product', 'label' => 'Product', 'sort' => 'product_name'],
        ['key' => 'kind', 'label' => 'Kind'],
        ['key' => 'package', 'label' => 'Package'],
        ['key' => 'reference', 'label' => 'Reference'],
        ['key' => 'status', 'label' => 'Status', 'sort' => 'status'],
        ['key' => 'approved-on', 'label' => 'Approved'],
        ['key' => 'expires-on', 'label' => 'Expires', 'sort' => 'expires_on'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
