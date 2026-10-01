<?php /** @var array $result  @var array $query  @var bool $canEdit */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('inventory/partials/reorder-row.php', ['stock' => $row, 'canEdit' => $canEdit]);
}
echo view('shared/list-card.php', [
    'screen' => 'reorder-list', 'title' => 'Below reorder point', 'url' => '/inventory/reorder', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'Nothing is below its reorder point.',
    'columns' => [
        ['key' => 'item', 'label' => 'Item', 'sort' => 'name'],
        ['key' => 'class', 'label' => 'Class'],
        ['key' => 'on-hand', 'label' => 'On hand'],
        ['key' => 'allocated', 'label' => 'Allocated'],
        ['key' => 'available', 'label' => 'Available', 'sort' => 'qty_available'],
        ['key' => 'on-order', 'label' => 'On order'],
        ['key' => 'reorder-point', 'label' => 'Reorder point'],
        ['key' => 'shortfall', 'label' => 'Shortfall', 'sort' => 'shortfall'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
