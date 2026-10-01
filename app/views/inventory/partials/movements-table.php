<?php /** @var array $result  @var array $query */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('inventory/partials/movements-row.php', ['movement' => $row]);
}
echo view('shared/list-card.php', [
    'screen' => 'inventory-movements', 'title' => 'Ledger movements', 'url' => '/inventory/movements', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No movements match.',
    'columns' => [
        ['key' => 'occurred', 'label' => 'Occurred', 'sort' => 'occurred_at'],
        ['key' => 'type', 'label' => 'Type'],
        ['key' => 'item', 'label' => 'Item', 'sort' => 'item_name'],
        ['key' => 'lot', 'label' => 'Lot'],
        ['key' => 'location', 'label' => 'Location'],
        ['key' => 'qty', 'label' => 'Quantity', 'sort' => 'qty_base'],
        ['key' => 'reason', 'label' => 'Reason'],
        ['key' => 'reference', 'label' => 'Reference'],
        ['key' => 'actor', 'label' => 'Actor'],
    ],
]);
