<?php /** @var array $result  @var array $query  @var bool $canEdit */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('products/partials/row.php', ['product' => $row, 'canEdit' => $canEdit]);
}
echo view('shared/list-card.php', [
    'screen' => 'products-list', 'title' => 'Products', 'url' => '/products/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No products match.',
    'columns' => [
        ['key' => 'name', 'label' => 'Name', 'sort' => 'name'],
        ['key' => 'style', 'label' => 'Style'],
        ['key' => 'beverage', 'label' => 'Beverage'],
        ['key' => 'tax-class', 'label' => 'Intended tax class'],
        ['key' => 'abv', 'label' => 'Target ABV'],
        ['key' => 'fruit-share', 'label' => 'Fruit share'],
        ['key' => 'recipe', 'label' => 'Active recipe'],
        ['key' => 'status', 'label' => 'Status', 'sort' => 'status'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
