<?php /** @var array $batch  @var array $input  @var array $errors  @var array $reasons  @var array $stages */
$p = 'batch-loss-form';
$options = array_map(static fn($r) => $r['name'] . ' (' . humanize($r['classification']) . ($r['requires_approval_above'] !== null ? ', approval above ' . fmt_qty($r['requires_approval_above'], 'L') : '') . ')', $reasons);
$body = form_input($p, 'qty_gal', 'Volume lost', $input['qty_gal'] ?? '', $errors, ['type' => 'number', 'step' => 'any', 'min' => '0', 'required' => true, 'icon' => 'feather-droplet', 'suffix' => display_unit('L')])
    . form_select($p, 'reason', 'Reason', $options, $input['reason'] ?? '', $errors, ['required' => true, 'blank' => 'Choose a reason'])
    . form_select($p, 'stage', 'Stage', array_map(static fn($s) => $s['name'], $stages), $input['stage'] ?? '', $errors, ['required' => true])
    . form_input($p, 'occurred_at', 'Occurred at', $input['occurred_at'] ?? '', $errors, ['type' => 'datetime-local', 'icon' => 'feather-clock', 'required' => true])
    . form_textarea($p, 'note', 'Note', $input['note'] ?? '', $errors, ['last' => true, 'help' => 'Required for exceptional losses.']);
echo view('batches/partials/event-form.php', ['batch' => $batch, 'formId' => $p, 'action' => '/batches/' . (int) $batch['id'] . '/losses/save', 'title' => 'Loss',
    'saveLabel' => 'Save Loss', 'intro' => 'The TTB category comes from the reason.', 'bodyHtml' => $body, 'errors' => $errors]);
