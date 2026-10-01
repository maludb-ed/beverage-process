<?php /** @var array $result  @var array $query  @var array $user */
$rowsHtml = '';
foreach ($result['rows'] as $lot) {
    $id = (int) $lot['id']; $r = 'release-lot-row-' . $id; $url = '/lots/' . $id . '/release';
    $rowsHtml .= '<tr id="' . e($r) . '">'
        . '<td id="' . e($r) . '-lot"><a ' . nav_attrs('/lots/' . $id) . '>' . status_dot(status_color($lot['quality_status'])) . '<span>' . e($lot['lot_number']) . '</span></a></td>'
        . '<td id="' . e($r) . '-item">' . e($lot['item_code']) . ' <small class="text-muted">' . e($lot['item_name']) . '</small></td>'
        . '<td id="' . e($r) . '-received">' . e(format_date($lot['received_on'])) . '</td>'
        . '<td id="' . e($r) . '-status">' . status_badge($lot['quality_status']) . '</td>'
        . '<td id="' . e($r) . '-coa">' . ($lot['has_coa'] ? badge('On file', 'success') : badge('None', 'secondary')) . '</td>'
        . '<td id="' . e($r) . '-actions" class="text-end"><div class="hstack gap-2 justify-content-end">'
        . (user_can($user, 'quality') ? nav_button($r . '-release-btn', $url, 'Review', 'feather-check-circle', 'btn btn-sm btn-primary') : '') . '</div></td></tr>';
}
echo view('shared/list-card.php', [
    'screen' => 'release-queue-lots', 'title' => 'Lots in quarantine or hold', 'url' => '/releases/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No lots are waiting for release.',
    'columns' => [
        ['key' => 'lot', 'label' => 'Lot'], ['key' => 'item', 'label' => 'Item'], ['key' => 'received', 'label' => 'Received'],
        ['key' => 'status', 'label' => 'Status'], ['key' => 'coa', 'label' => 'CoA'], ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
