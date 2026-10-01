<?php /** @var array $result  @var array $query  @var bool $canEdit */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('kegs/partials/row.php', ['keg' => $row, 'canEdit' => $canEdit]);
}
echo view('shared/list-card.php', [
    'screen' => 'kegs-list', 'title' => 'Keg fleet', 'url' => '/kegs/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No kegs match. Add a keg to start the fleet.',
    'columns' => [
        ['key' => 'serial', 'label' => 'Serial', 'sort' => 'serial'],
        ['key' => 'size', 'label' => 'Size'],
        ['key' => 'state', 'label' => 'State', 'sort' => 'state'],
        ['key' => 'contents', 'label' => 'Contents'],
        ['key' => 'holder', 'label' => 'Holder'],
        ['key' => 'days', 'label' => 'Days', 'sort' => 'days_since_moved'],
        ['key' => 'fills', 'label' => 'Fills'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
