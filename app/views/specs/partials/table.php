<?php /** @var array $specs  @var string $rowsHtml  @var int $productId */
$total = count($specs);
echo view('shared/list-card.php', [
    'screen' => 'specs-list', 'title' => 'Specs by stage', 'url' => '/products/' . $productId . '/specs', 'query' => [],
    'paging' => ['total' => $total, 'page' => 1, 'pages' => 1, 'page_size' => max(1, $total), 'rows' => []], 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No specs yet for this product.',
    'columns' => [
        ['key' => 'stage', 'label' => 'Stage'],
        ['key' => 'measurement', 'label' => 'Measurement'],
        ['key' => 'min', 'label' => 'Min'],
        ['key' => 'max', 'label' => 'Max'],
        ['key' => 'target', 'label' => 'Target'],
        ['key' => 'active', 'label' => 'Active'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
