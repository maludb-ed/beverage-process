<?php /** @var array $result  @var array $query  @var bool $canEdit */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('receipts/partials/row.php', ['receipt' => $row, 'canEdit' => $canEdit]);
}
echo view('shared/list-card.php', [
    'screen' => 'receipts-list', 'title' => 'Goods receipts', 'url' => '/receipts/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No receipts match.',
    'columns' => [
        ['key' => 'number', 'label' => 'Number', 'sort' => 'number'],
        ['key' => 'supplier', 'label' => 'Supplier', 'sort' => 'supplier'],
        ['key' => 'purchase-order', 'label' => 'Purchase order'],
        ['key' => 'received-at', 'label' => 'Received', 'sort' => 'received_at'],
        ['key' => 'status', 'label' => 'Status', 'sort' => 'status'],
        ['key' => 'lines', 'label' => 'Lines'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
