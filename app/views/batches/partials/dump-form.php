<?php /** @var array $batch  @var array $input  @var array $errors  @var array $reasons */
$p = 'batch-dump-form';
$body = form_select($p, 'reason', 'Reason', array_map(static fn($r) => $r['name'], $reasons), $input['reason'] ?? (count($reasons) === 1 ? array_key_first($reasons) : ''), $errors, ['required' => true, 'blank' => count($reasons) === 1 ? null : 'Choose a reason'])
    . form_textarea($p, 'note', 'Note', $input['note'] ?? '', $errors, ['help' => 'Required: why the batch is dumped.'])
    . form_input($p, 'dumped_at', 'Dumped at', $input['dumped_at'] ?? '', $errors, ['type' => 'datetime-local', 'icon' => 'feather-clock', 'required' => true, 'last' => true]);
echo view('batches/partials/event-form.php', ['batch' => $batch, 'formId' => $p, 'action' => '/batches/' . (int) $batch['id'] . '/dump', 'title' => 'Dump batch',
    'saveLabel' => 'Dump Batch', 'intro' => 'Records the whole volume as destroyed and sends its vessels to cleaning. This cannot be undone.', 'bodyHtml' => $body, 'errors' => $errors,
    'confirm' => 'Dump ' . $batch['number'] . ' (' . fmt_qty($batch['current_volume_l'], 'L') . ')? This cannot be undone.']);
