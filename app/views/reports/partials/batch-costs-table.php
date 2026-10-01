<?php
/** @var array $result  @var array $query  @var array $packageCosts  @var array $totals  @var string $csvUrl */
$money = static fn($v): string => $v === null || $v === '' ? '' : ((float) $v < 0 ? '-' : '') . '$' . number_format(abs((float) $v), 2);
$varianceCell = static function ($v) use ($money): string {
    if ($v === null || $v === '') { return ''; }
    $class = (float) $v > 0 ? 'text-danger' : ((float) $v < 0 ? 'text-success' : '');
    return '<span class="' . $class . '">' . e($money($v)) . '</span>';
};
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $id = (int) $row['batch_id'];
    $rid = 'report-batch-costs-row-' . $id;
    $perGal = $row['liquid_cost_per_l'] === null ? null : (float) $row['liquid_cost_per_l'] * LITERS_PER_GALLON;
    $pkg = $packageCosts[$id] ?? ['per_keg' => null, 'per_case' => null];
    $rowsHtml .= '<tr id="' . e($rid) . '">'
        . '<td id="' . e($rid) . '-batch"><a ' . nav_attrs('/batches/' . $id) . '>' . status_dot(REPORT_BATCH_STATUS_COLORS[(string) $row['status']] ?? 'secondary') . '<span>' . e($row['number']) . '</span></a> <small class="text-muted">' . e($row['product_name']) . '</small></td>'
        . '<td id="' . e($rid) . '-status">' . badge(humanize((string) $row['status']), REPORT_BATCH_STATUS_COLORS[(string) $row['status']] ?? 'secondary') . '</td>'
        . '<td id="' . e($rid) . '-volume">' . fmt_qty_html($row['starting_volume_l'], 'L') . '</td>'
        . '<td id="' . e($rid) . '-material">' . e($money($row['material_cost'])) . '</td>'
        . '<td id="' . e($rid) . '-packaging">' . e($money($row['packaging_cost'])) . '</td>'
        . '<td id="' . e($rid) . '-overhead">' . e($money($row['overhead_cost'])) . '</td>'
        . '<td id="' . e($rid) . '-total">' . e($money($row['total_cost'])) . '</td>'
        . '<td id="' . e($rid) . '-per-gal">' . e($money($perGal)) . '</td>'
        . '<td id="' . e($rid) . '-standard">' . e($money($row['standard_cost_total'])) . '</td>'
        . '<td id="' . e($rid) . '-variance">' . $varianceCell($row['variance_to_standard']) . '</td>'
        . '<td id="' . e($rid) . '-per-keg">' . e($money($pkg['per_keg'])) . '</td>'
        . '<td id="' . e($rid) . '-per-case">' . e($money($pkg['per_case'])) . '</td></tr>';
}
if ($rowsHtml !== '') {
    $rowsHtml .= '<tr class="fw-semibold" id="report-batch-costs-totals"><td colspan="2">Total, ' . e(number_format((int) $totals['batches'])) . ' batches</td><td></td>'
        . '<td>' . e($money($totals['material_cost'])) . '</td><td>' . e($money($totals['packaging_cost'])) . '</td><td>' . e($money($totals['overhead_cost'])) . '</td>'
        . '<td>' . e($money($totals['total_cost'])) . '</td><td></td><td></td><td>' . $varianceCell($totals['variance_to_standard']) . '</td><td></td><td></td></tr>';
}
echo view('shared/list-card.php', [
    'screen' => 'report-batch-costs', 'title' => 'Batch costs', 'url' => '/reports/batch-costs', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No batches match. Costs appear once batches are started and consume materials.',
    'columns' => [
        ['key' => 'batch', 'label' => 'Batch', 'sort' => 'number'],
        ['key' => 'status', 'label' => 'Status'],
        ['key' => 'volume', 'label' => 'Starting volume'],
        ['key' => 'material', 'label' => 'Material'],
        ['key' => 'packaging', 'label' => 'Packaging'],
        ['key' => 'overhead', 'label' => 'Overhead'],
        ['key' => 'total', 'label' => 'Total', 'sort' => 'total_cost'],
        ['key' => 'per-gal', 'label' => 'Per gal'],
        ['key' => 'standard', 'label' => 'Standard'],
        ['key' => 'variance', 'label' => 'Variance', 'sort' => 'variance_to_standard'],
        ['key' => 'per-keg', 'label' => 'Per keg'],
        ['key' => 'per-case', 'label' => 'Per case'],
    ],
]);
