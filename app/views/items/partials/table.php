<?php /** @var array $result  @var array $query  @var array $classes  @var bool $canEdit */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('items/partials/row.php', ['item' => $row, 'classes' => $classes, 'canEdit' => $canEdit]);
}
echo view('shared/list-card.php', [
    'screen' => 'items-list', 'title' => 'All items', 'url' => '/items/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No items found. Add the first item.',
    'columns' => [
        ['key' => 'code', 'label' => 'Code', 'sort' => 'code'],
        ['key' => 'name', 'label' => 'Name', 'sort' => 'name'],
        ['key' => 'item-class', 'label' => 'Class', 'sort' => 'item_class'],
        ['key' => 'base-unit-code', 'label' => 'Base unit'],
        ['key' => 'lot-controlled', 'label' => 'Lot controlled'],
        ['key' => 'default-receipt-status', 'label' => 'Receipt status'],
        ['key' => 'reorder-point', 'label' => 'Reorder point'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
