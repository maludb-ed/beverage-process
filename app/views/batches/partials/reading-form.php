<?php /** @var array $batch  @var array $input  @var array $errors  @var array $measurements  @var array $stages */
$p = 'batch-reading-form';
$options = array_map(static fn($m) => $m['name'] . ' (' . $m['unit'] . ')', $measurements);
$body = form_select($p, 'measurement', 'Measurement', $options, $input['measurement'] ?? '', $errors, ['required' => true, 'blank' => 'Choose a measurement'])
    . form_input($p, 'value', 'Value', $input['value'] ?? '', $errors, ['type' => 'number', 'step' => 'any', 'required' => true, 'icon' => 'feather-hash'])
    . form_input($p, 'taken_at', 'Taken at', $input['taken_at'] ?? '', $errors, ['type' => 'datetime-local', 'icon' => 'feather-clock', 'required' => true])
    . form_select($p, 'stage', 'Stage', array_map(static fn($s) => $s['name'], $stages), $input['stage'] ?? '', $errors, ['blank' => 'No stage'])
    . form_input($p, 'method', 'Method', $input['method'] ?? '', $errors, ['maxlength' => 80, 'icon' => 'feather-tool', 'placeholder' => 'Hydrometer, refractometer, meter…'])
    . form_textarea($p, 'note', 'Note', $input['note'] ?? '', $errors, ['last' => true]);
echo view('batches/partials/event-form.php', ['batch' => $batch, 'formId' => $p, 'action' => '/batches/' . (int) $batch['id'] . '/readings/save', 'title' => 'Reading',
    'saveLabel' => 'Save Reading', 'intro' => 'Out-of-spec values are flagged against the product spec for the stage.', 'bodyHtml' => $body, 'errors' => $errors]);
