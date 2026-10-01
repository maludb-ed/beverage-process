<?php /** @var array $result  @var array $query  @var bool $canEdit */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('standard-costs/partials/row.php', ['item' => $row, 'canEdit' => $canEdit]);
}
echo view('shared/list-card.php', [
    'screen' => 'standard-costs-list', 'title' => 'Item standard costs', 'url' => '/standard-costs/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No items match.',
    'columns' => [
        ['key' => 'item', 'label' => 'Item', 'sort' => 'name'],
        ['key' => 'class', 'label' => 'Class', 'sort' => 'item_class'],
        ['key' => 'method', 'label' => 'Costing method'],
        ['key' => 'standard', 'label' => 'Current standard'],
        ['key' => 'effective-from', 'label' => 'Effective from'],
        ['key' => 'history', 'label' => 'History'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
