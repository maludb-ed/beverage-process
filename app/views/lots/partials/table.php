<?php /** @var array $result  @var array $query */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('lots/partials/row.php', ['lot' => $row]);
}
echo view('shared/list-card.php', [
    'screen' => 'lots-list', 'title' => 'Lots', 'url' => '/lots/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No lots match. Lots are created by posting receipts, press runs, batches and packaging runs.',
    'columns' => [
        ['key' => 'lot-number', 'label' => 'Lot', 'sort' => 'lot_number'],
        ['key' => 'item', 'label' => 'Item', 'sort' => 'item'],
        ['key' => 'quality-status', 'label' => 'Status', 'sort' => 'quality_status'],
        ['key' => 'received-on', 'label' => 'Received / produced', 'sort' => 'received_on'],
        ['key' => 'expires-on', 'label' => 'Expires', 'sort' => 'expires_on'],
        ['key' => 'on-hand', 'label' => 'On hand'],
        ['key' => 'supplier', 'label' => 'Supplier'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
