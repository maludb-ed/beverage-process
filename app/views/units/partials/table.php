<?php /** @var array $units */
$rowsHtml = '';
foreach ($units as $unit) {
    $code = (string) $unit['code'];
    $rowsHtml .= '<tr id="unit-row-' . e($code) . '">'
        . '<td id="unit-row-' . e($code) . '-code"><span class="fw-semibold">' . e($code) . '</span></td>'
        . '<td id="unit-row-' . e($code) . '-name">' . e($unit['name']) . '</td>'
        . '<td id="unit-row-' . e($code) . '-dimension">' . e(humanize($unit['dimension'])) . '</td>'
        . '<td id="unit-row-' . e($code) . '-to-base-factor">' . e(rtrim(rtrim(number_format((float) $unit['to_base_factor'], 8, '.', ''), '0'), '.')) . '</td>'
        . '<td id="unit-row-' . e($code) . '-is-base">' . e(yes_no($unit['is_base'])) . '</td>'
        . '</tr>';
}
echo view('shared/list-card.php', [
    'screen' => 'units-list', 'title' => 'All units', 'url' => '/units/', 'query' => [], 'rowsHtml' => $rowsHtml,
    'paging' => ['total' => count($units), 'page' => 1, 'pages' => 1, 'page_size' => max(1, count($units))],
    'emptyMessage' => 'No units defined.',
    'columns' => [
        ['key' => 'code', 'label' => 'Code'],
        ['key' => 'name', 'label' => 'Name'],
        ['key' => 'dimension', 'label' => 'Dimension'],
        ['key' => 'to-base-factor', 'label' => 'To base factor'],
        ['key' => 'is-base', 'label' => 'Base unit'],
    ],
]);
