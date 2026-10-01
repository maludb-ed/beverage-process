<?php /** @var array $batch  @var array $input  @var array $rows  @var array $errors  @var array $rowErrors  @var array $vessels */
$p = 'batch-split-form';
$id = (int) $batch['id'];
$rowsHtml = '';
foreach ($rows as $n => $row) {
    $rowsHtml .= view('batches/partials/split-row.php', ['n' => $n, 'row' => $row, 'vessels' => $vessels, 'rowErrors' => $rowErrors[$n] ?? [], 'prefix' => 'batch-split-form-output-row', 'base' => 'outputs', 'kind' => 'split', 'batches' => []]);
}
$body = form_input($p, 'split_at', 'Split at', $input['split_at'] ?? '', $errors, ['type' => 'datetime-local', 'icon' => 'feather-clock', 'required' => true])
    . form_textarea($p, 'note', 'Note', $input['note'] ?? '', $errors)
    . '<div class="d-flex align-items-center justify-content-between mb-3"><h6 class="fw-bold mb-0">New batches</h6>'
    . '<button type="button" class="btn btn-sm btn-light-brand" id="batch-split-form-add-output-btn" hx-get="/batches/split-row" hx-target="#batch-split-form-outputs" hx-swap="beforeend" hx-vals=\'js:{n: "n" + Date.now()}\'><i class="feather-plus me-1"></i>Add output</button></div>'
    . '<div id="batch-split-form-outputs">' . $rowsHtml . '</div>'
    . '<p class="fs-12 text-muted mb-0">Whatever is not split off stays in ' . e($batch['number']) . ' in its vessel.</p>';
echo view('batches/partials/event-form.php', ['batch' => $batch, 'formId' => $p, 'action' => '/batches/' . $id . '/split', 'title' => 'Split',
    'saveLabel' => 'Save Split', 'intro' => 'Each output becomes a new batch in its own vessel.', 'bodyHtml' => $body, 'errors' => $errors]);
