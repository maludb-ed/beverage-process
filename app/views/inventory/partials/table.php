<?php /** @var array $result  @var array $query */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('inventory/partials/row.php', ['balance' => $row]);
}
echo view('shared/list-card.php', [
    'screen' => 'inventory-list', 'title' => 'On hand', 'url' => '/inventory/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No stock matches.',
    'columns' => [
        ['key' => 'item', 'label' => 'Item', 'sort' => 'item_name'],
        ['key' => 'lot', 'label' => 'Lot', 'sort' => 'lot_number'],
        ['key' => 'location', 'label' => 'Location', 'sort' => 'location_name'],
        ['key' => 'on-hand', 'label' => 'On hand', 'sort' => 'qty_on_hand'],
        ['key' => 'allocated', 'label' => 'Allocated'],
        ['key' => 'available', 'label' => 'Available'],
        ['key' => 'expires', 'label' => 'Expires', 'sort' => 'expires_on'],
        ['key' => 'value', 'label' => 'Value', 'class' => 'text-end'],
    ],
]);
