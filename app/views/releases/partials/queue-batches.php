<?php /** @var array $result  @var array $query  @var array $user */
$rowsHtml = '';
foreach ($result['rows'] as $row) {
    $rowsHtml .= view('releases/partials/queue-batch-row.php', ['batch' => $row, 'user' => $user]);
}
echo view('shared/list-card.php', [
    'screen' => 'release-queue-batches', 'title' => 'Batches awaiting release', 'url' => '/releases/', 'query' => $query, 'paging' => $result, 'rowsHtml' => $rowsHtml,
    'emptyMessage' => 'No batches are waiting for release.',
    'columns' => [
        ['key' => 'batch', 'label' => 'Batch', 'sort' => 'number'],
        ['key' => 'stage', 'label' => 'Stage', 'sort' => 'entered_at'],
        ['key' => 'volume', 'label' => 'Volume'],
        ['key' => 'readings', 'label' => 'Last readings'],
        ['key' => 'failing', 'label' => 'Failing specs'],
        ['key' => 'sensory', 'label' => 'Sensory'],
        ['key' => 'actions', 'label' => 'Actions', 'class' => 'text-end'],
    ],
]);
