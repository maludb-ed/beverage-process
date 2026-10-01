<?php
/** @var array $result  @var array $query */
$tonClass = static function ($v): string {
    if ($v === null) { return ''; }
    return (float) $v < 130 ? 'text-warning' : ((float) $v > 185 ? 'text-info' : 'text-success');
};
$rowsHtml = '';
foreach ($result['rows'] as $i => $row) {
    $rid = 'report-juice-yield-row-' . (int) $row['press_run_id'] . '-' . $i;
    $rowsHtml .= '<tr id="' . e($rid) . '">'
        . '<td id="' . e($rid) . '-run"><a ' . nav_attrs('/press-runs/' . (int) $row['press_run_id']) . '>' . e($row['number']) . '</a></td>'
        . '<td id="' . e($rid) . '-date">' . e(format_date($row['run_on'])) . '</td>'
        . '<td id="' . e($rid) . '-variety">' . ($row['variety'] === null ? '<span class="text-muted">Unspecified</span>' : e($row['variety'])) . '</td>'
        . '<td id="' . e($rid) . '-fruit">' . fmt_qty_html($row['fruit_kg'], 'kg', 0, 'fruit') . '</td>'
        . '<td id="' . e($rid) . '-juice">' . fmt_qty_html($row['juice_l_attributed'], 'L', 0) . '</td>'
        . '<td id="' . e($rid) . '-gal-per-ton" class="' . $tonClass($row['gal_per_ton']) . '">' . ($row['gal_per_ton'] === null ? '' : e(number_format((float) $row['gal_per_ton'], 1))) . '</td>'
        . '<td id="' . e($rid) . '-gal-per-bushel">' . ($row['gal_per_bushel'] === null ? '' : e(number_format((float) $row['gal_per_bushel'], 2))) . '</td></tr>';
}
echo view('shared/list-card.php', [
    'screen' => 'report-juice-yield-detail', 'title' => 'Press runs', 'url' => '/reports/juice-yield', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No posted press runs in this season.',
    'columns' => [
        ['key' => 'run', 'label' => 'Press run'],
        ['key' => 'date', 'label' => 'Date', 'sort' => 'run_on'],
        ['key' => 'variety', 'label' => 'Variety', 'sort' => 'variety'],
        ['key' => 'fruit', 'label' => 'Fruit'],
        ['key' => 'juice', 'label' => 'Juice'],
        ['key' => 'gal-per-ton', 'label' => 'Gal / ton', 'sort' => 'gal_per_ton'],
        ['key' => 'gal-per-bushel', 'label' => 'Gal / bushel'],
    ],
]);
