<?php /** @var array $result  @var array $query  @var bool $canEdit  @var bool $canPrice */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('orders/partials/row.php', ['order' => $row, 'canEdit' => $canEdit, 'canPrice' => $canPrice]);
}
$columns = [
    ['key' => 'number', 'label' => 'Number', 'sort' => 'number'],
    ['key' => 'customer', 'label' => 'Customer', 'sort' => 'customer'],
    ['key' => 'reference', 'label' => 'Reference'],
    ['key' => 'status', 'label' => 'Status', 'sort' => 'status'],
    ['key' => 'ordered-on', 'label' => 'Ordered', 'sort' => 'ordered_on'],
    ['key' => 'requested-on', 'label' => 'Due', 'sort' => 'requested_on'],
    ['key' => 'units', 'label' => 'Units (open)'],
];
if ($canPrice) {
    $columns[] = ['key' => 'value', 'label' => 'Value', 'class' => 'text-end'];
}
$columns[] = ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'];
echo view('shared/list-card.php', [
    'screen' => 'orders-list', 'title' => 'Customer orders', 'url' => '/orders/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No orders match.', 'columns' => $columns,
]);
