<?php /** @var array $result  @var array $query  @var bool $canEdit */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('packaging-configs/partials/row.php', ['config' => $row, 'canEdit' => $canEdit]);
}
echo view('shared/list-card.php', [
    'screen' => 'packaging-configs-list', 'title' => 'Packaging configurations', 'url' => '/packaging-configs/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No packaging configurations match.',
    'columns' => [
        ['key' => 'name', 'label' => 'Name', 'sort' => 'name'],
        ['key' => 'product', 'label' => 'Product', 'sort' => 'product_name'],
        ['key' => 'item', 'label' => 'Finished item'],
        ['key' => 'kind', 'label' => 'Kind', 'sort' => 'package_kind'],
        ['key' => 'fill', 'label' => 'Fill volume'],
        ['key' => 'units', 'label' => 'Units per case'],
        ['key' => 'loss', 'label' => 'Expected loss'],
        ['key' => 'bom', 'label' => 'BOM lines'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
