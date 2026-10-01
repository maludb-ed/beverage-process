<?php
/** @var array $result  @var array $query  @var array $totals  @var string $csvUrl */
$loss = static fn($v): string => $v === null || $v === '' ? '' : number_format((float) $v, 2) . '%';
$groups = [];
foreach ($result['rows'] as $row) {
    $groups[(int) $row['batch_id']][] = $row;
}
$rowsHtml = '';
foreach ($groups as $batchId => $rows) {
    $first = $rows[0];
    $rowsHtml .= '<tr class="table-light" id="report-yields-batch-' . e($batchId) . '"><td colspan="9"><a ' . nav_attrs('/batches/' . $batchId) . '>' . status_dot(REPORT_BATCH_STATUS_COLORS[(string) $first['status']] ?? 'secondary') . '<span class="fw-semibold">' . e($first['number']) . '</span></a> <small class="text-muted">' . e($first['product_name']) . '</small></td></tr>';
    foreach ($rows as $i => $row) {
        $rid = 'report-yields-row-' . $batchId . '-' . $i;
        $variance = $row['actual_loss_pct'] !== null && $row['expected_loss_pct'] !== null ? (float) $row['actual_loss_pct'] - (float) $row['expected_loss_pct'] : null;
        $varClass = $variance === null ? '' : ($variance <= 0 ? 'text-success' : ($variance <= 2 ? 'text-warning' : 'text-danger'));
        $rowsHtml .= '<tr id="' . e($rid) . '">'
            . '<td id="' . e($rid) . '-batch"><a ' . nav_attrs('/batches/' . $batchId) . '>' . e($row['number']) . '</a> <small class="text-muted">' . e($row['product_name']) . '</small></td>'
            . '<td id="' . e($rid) . '-stage">' . e($row['stage_name']) . '</td>'
            . '<td id="' . e($rid) . '-entered">' . e(format_date($row['entered_at'])) . '</td>'
            . '<td id="' . e($rid) . '-volume-in">' . fmt_qty_html($row['volume_in_l'], 'L') . '</td>'
            . '<td id="' . e($rid) . '-volume-out">' . fmt_qty_html($row['volume_out_l'], 'L') . '</td>'
            . '<td id="' . e($rid) . '-actual-loss">' . e($loss($row['actual_loss_pct'])) . '</td>'
            . '<td id="' . e($rid) . '-expected-loss">' . e($loss($row['expected_loss_pct'])) . '</td>'
            . '<td id="' . e($rid) . '-variance" class="' . $varClass . '">' . ($variance === null ? '' : e(($variance > 0 ? '+' : '') . number_format($variance, 2) . ' pts')) . '</td>'
            . '<td id="' . e($rid) . '-recorded-loss">' . fmt_qty_html($row['recorded_loss_l'], 'L') . '</td></tr>';
    }
    $t = $totals[$batchId] ?? null;
    if ($t !== null) {
        $rowsHtml .= '<tr class="fw-semibold" id="report-yields-batch-' . e($batchId) . '-total"><td colspan="3">Batch total</td>'
            . '<td>' . fmt_qty_html($t['volume_in_l'], 'L') . '</td><td>' . fmt_qty_html($t['volume_out_l'], 'L') . '</td>'
            . '<td>' . e($loss($t['loss_pct'])) . '</td><td></td><td></td><td>' . fmt_qty_html($t['recorded_loss_l'], 'L') . '</td></tr>';
    }
}
echo view('shared/list-card.php', [
    'screen' => 'report-yields', 'title' => 'Stage yields', 'url' => '/reports/yields', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No stage events match. Yields appear once batches have recorded stage events.',
    'columns' => [
        ['key' => 'batch', 'label' => 'Batch', 'sort' => 'number'],
        ['key' => 'stage', 'label' => 'Stage', 'sort' => 'stage_code'],
        ['key' => 'entered', 'label' => 'Entered', 'sort' => 'entered_at'],
        ['key' => 'volume-in', 'label' => 'Volume in'],
        ['key' => 'volume-out', 'label' => 'Volume out'],
        ['key' => 'actual-loss', 'label' => 'Actual loss'],
        ['key' => 'expected-loss', 'label' => 'Expected loss'],
        ['key' => 'variance', 'label' => 'Variance'],
        ['key' => 'recorded-loss', 'label' => 'Recorded loss'],
    ],
]);
