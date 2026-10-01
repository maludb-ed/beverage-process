<?php /** @var array $result  @var array $query  @var bool $canEdit */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('production-orders/partials/row.php', ['order' => $row, 'canEdit' => $canEdit]);
}
echo view('shared/list-card.php', [
    'screen' => 'production-orders-list', 'title' => 'Production orders', 'url' => '/production-orders/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No production orders match.',
    'columns' => [
        ['key' => 'number', 'label' => 'Number', 'sort' => 'number'],
        ['key' => 'product', 'label' => 'Product', 'sort' => 'product_name'],
        ['key' => 'recipe', 'label' => 'Recipe'],
        ['key' => 'volume', 'label' => 'Planned volume'],
        ['key' => 'pitch-on', 'label' => 'Pitch', 'sort' => 'planned_pitch_on'],
        ['key' => 'package-on', 'label' => 'Package', 'sort' => 'planned_package_on'],
        ['key' => 'status', 'label' => 'Status', 'sort' => 'status'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
