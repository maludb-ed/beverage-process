<?php /** @var array $result  @var array $query  @var bool $canEdit */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('batches/partials/row.php', ['batch' => $row, 'canEdit' => $canEdit]);
}
echo view('shared/list-card.php', [
    'screen' => 'batches-list', 'title' => 'Batches', 'url' => '/batches/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No batches match.',
    'columns' => [
        ['key' => 'number', 'label' => 'Number', 'sort' => 'number'],
        ['key' => 'product', 'label' => 'Product', 'sort' => 'product_name'],
        ['key' => 'stage', 'label' => 'Stage', 'sort' => 'current_stage_code'],
        ['key' => 'vessels', 'label' => 'Vessel'],
        ['key' => 'volume', 'label' => 'Volume'],
        ['key' => 'started', 'label' => 'Started', 'sort' => 'started_at'],
        ['key' => 'tax-class', 'label' => 'Tax class'],
        ['key' => 'status', 'label' => 'Status', 'sort' => 'status'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
