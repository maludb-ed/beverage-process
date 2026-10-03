<?php
/** @var array $rows  @var array $query  @var float $total */
$money = static fn($v): string => ((float) $v < 0 ? '-' : '') . '$' . number_format(abs((float) $v), 2);
$groupBy = $query['group_by'];
$rowsHtml = '';
foreach ($rows as $i => $row) {
    $rid = 'report-valuation-row-' . $i;
    $label = (string) $row['group_label'];
    $url = match ($groupBy) {
        'item_class' => '/inventory/' . ($label === 'finished_good' ? 'finished' : 'materials') . query_string(['item_class' => $label]),
        'location' => $row['location_id'] !== null ? '/inventory/materials' . query_string(['location_id' => (int) $row['location_id']]) : null,
        default => null,
    };
    $text = $groupBy === 'tax_state' ? badge(humanize($label), $label === 'bonded' ? 'info' : 'success') : e($groupBy === 'location' ? $label : humanize($label));
    $share = $total != 0.0 ? number_format(100 * (float) $row['value'] / $total, 1) . '%' : '';
    $rowsHtml .= '<tr id="' . e($rid) . '">'
        . '<td id="' . e($rid) . '-group">' . ($url ? '<a ' . nav_attrs($url) . '>' . $text . '</a>' : $text) . '</td>'
        . '<td id="' . e($rid) . '-quantity">' . fmt_qty_html($row['qty_on_hand'], $row['base_unit_code'], 1) . '</td>'
        . '<td id="' . e($rid) . '-value">' . e($money($row['value'])) . '</td>'
        . '<td id="' . e($rid) . '-share">' . e($share) . '</td></tr>';
}
if ($rowsHtml !== '') {
    $rowsHtml .= '<tr class="fw-semibold" id="report-valuation-total"><td colspan="2">Total value</td><td>' . e($money($total)) . '</td><td></td></tr>';
}
echo view('shared/list-card.php', [
    'screen' => 'report-valuation-groups', 'title' => 'Inventory value by ' . strtolower(REPORT_GROUP_BY[$groupBy]), 'url' => '/reports/valuation', 'query' => $query,
    'paging' => ['total' => count($rows), 'page' => 1, 'pages' => 1, 'page_size' => max(1, count($rows))], 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No inventory on hand for this date.',
    'columns' => [
        ['key' => 'group', 'label' => REPORT_GROUP_BY[$groupBy], 'sort' => 'group'],
        ['key' => 'quantity', 'label' => 'Quantity on hand'],
        ['key' => 'value', 'label' => 'Value', 'sort' => 'value'],
        ['key' => 'share', 'label' => 'Share'],
    ],
]);
