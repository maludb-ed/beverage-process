<?php /** @var array $result  @var array $query  @var string $kind */
$meta = INVENTORY_KINDS[$kind];
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('inventory/partials/row.php', ['balance' => $row]);
}
echo view('shared/list-card.php', [
    'screen' => $meta['screen'], 'title' => $meta['title'] . ' on hand', 'url' => '/inventory/' . $kind, 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => $meta['empty'],
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
