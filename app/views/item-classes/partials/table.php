<?php /** @var array $result  @var array $query  @var bool $canEdit */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('item-classes/partials/row.php', ['class' => $row, 'canEdit' => $canEdit]);
}
echo view('shared/list-card.php', [
    'screen' => 'item-classes-list', 'title' => 'Item classes', 'url' => '/item-classes/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No item classes match.',
    'columns' => [
        ['key' => 'order', 'label' => 'Order', 'sort' => 'display_order'],
        ['key' => 'code', 'label' => 'Code', 'sort' => 'code'],
        ['key' => 'name', 'label' => 'Name', 'sort' => 'name'],
        ['key' => 'kind', 'label' => 'Kind', 'sort' => 'kind'],
        ['key' => 'purchasable', 'label' => 'Purchasable'],
        ['key' => 'recipe-ingredient', 'label' => 'Recipe ingredient'],
        ['key' => 'items', 'label' => 'Items', 'sort' => 'items'],
        ['key' => 'origin', 'label' => 'Origin'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
