<?php /** @var array $result  @var array $query */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('finished-lots/partials/row.php', ['lot' => $row]);
}
echo view('shared/list-card.php', [
    'screen' => 'finished-lots-list', 'title' => 'Finished goods', 'url' => '/finished-lots/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No finished goods yet. Post a packaging run to create a finished lot.',
    'columns' => [
        ['key' => 'lot', 'label' => 'Lot', 'sort' => 'lot_number'],
        ['key' => 'product', 'label' => 'Product', 'sort' => 'product_name'],
        ['key' => 'batch', 'label' => 'Batch'],
        ['key' => 'packaged', 'label' => 'Packaged', 'sort' => 'packaged_on'],
        ['key' => 'on-hand', 'label' => 'On hand'],
        ['key' => 'volume', 'label' => 'Volume'],
        ['key' => 'tax-class', 'label' => 'Tax class'],
        ['key' => 'location', 'label' => 'Location'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
