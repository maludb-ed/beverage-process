<?php /** @var array $result  @var array $query  @var bool $canEdit */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $id = (int) $row['id'];
    $running = $row['active'] && ($row['ends_on'] === null || $row['ends_on'] >= today());
    $rowsHtml .= '<tr id="standing-order-row-' . $id . '">'
        . '<td id="standing-order-row-' . $id . '-number"><a ' . nav_attrs('/orders/standing/' . $id) . '>' . status_dot($running ? 'success' : 'secondary') . '<span>' . e($row['number']) . '</span></a></td>'
        . '<td id="standing-order-row-' . $id . '-customer">' . e($row['customer_name']) . '</td>'
        . '<td id="standing-order-row-' . $id . '-schedule">' . e(standing_schedule_label($row)) . '</td>'
        . '<td id="standing-order-row-' . $id . '-units">' . e(number_format((int) $row['units_each'])) . '</td>'
        . '<td id="standing-order-row-' . $id . '-next">' . e($running && $row['next_on'] ? format_date($row['next_on']) : '—') . '</td>'
        . '<td id="standing-order-row-' . $id . '-state">' . badge($running ? 'Running' : ($row['active'] ? 'Ended' : 'Paused'), $running ? 'success' : 'secondary') . '</td>'
        . '<td id="standing-order-row-' . $id . '-actions" class="text-end"><div class="hstack gap-2 justify-content-end">'
        . ($canEdit ? row_edit_button('standing-order-row-' . $id . '-edit-btn', '/orders/standing/' . $id . '/edit') : '') . '</div></td></tr>';
}
echo view('shared/list-card.php', [
    'screen' => 'standing-orders-list', 'title' => 'Standing orders', 'url' => '/orders/standing', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No standing orders match.',
    'columns' => [
        ['key' => 'number', 'label' => 'Number', 'sort' => 'number'],
        ['key' => 'customer', 'label' => 'Customer', 'sort' => 'customer'],
        ['key' => 'schedule', 'label' => 'Schedule'],
        ['key' => 'units', 'label' => 'Units each time'],
        ['key' => 'next', 'label' => 'Next date'],
        ['key' => 'state', 'label' => 'State'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
