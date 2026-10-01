<?php /** @var array $result  @var array $query  @var bool $canEdit */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('purchase-orders/partials/row.php', ['order' => $row, 'canEdit' => $canEdit]);
}
echo view('shared/list-card.php', [
    'screen' => 'purchase-orders-list', 'title' => 'Purchase orders', 'url' => '/purchase-orders/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No purchase orders match.',
    'columns' => [
        ['key' => 'number', 'label' => 'Number', 'sort' => 'number'],
        ['key' => 'supplier', 'label' => 'Supplier', 'sort' => 'supplier'],
        ['key' => 'status', 'label' => 'Status', 'sort' => 'status'],
        ['key' => 'ordered-on', 'label' => 'Ordered', 'sort' => 'ordered_on'],
        ['key' => 'expected-on', 'label' => 'Expected', 'sort' => 'expected_on'],
        ['key' => 'lines', 'label' => 'Lines'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
