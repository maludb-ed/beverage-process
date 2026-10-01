<?php /** @var array $batch  @var array $input  @var array $errors  @var array $fromOptions  @var array $toOptions */
$p = 'batch-transfer-form';
$unit = display_unit('L');
$body = form_select($p, 'from_vessel', 'From', $fromOptions, $input['from_vessel_id'] ?? '', $errors, ['name' => 'from_vessel_id', 'required' => true, 'blank' => count($fromOptions) === 1 ? null : 'Choose a vessel'])
    . form_select($p, 'to_vessel', 'To', $toOptions, $input['to_vessel_id'] ?? '', $errors, ['name' => 'to_vessel_id', 'required' => true, 'blank' => 'Choose the receiving vessel'])
    . form_input($p, 'volume_gal', 'Volume moved', $input['volume_gal'] ?? '', $errors, ['type' => 'number', 'step' => 'any', 'min' => '0', 'required' => true, 'icon' => 'feather-droplet', 'suffix' => $unit])
    . form_input($p, 'loss_gal', 'Loss', $input['loss_gal'] ?? '0', $errors, ['type' => 'number', 'step' => 'any', 'min' => '0', 'icon' => 'feather-minus-circle', 'suffix' => $unit, 'help' => 'Left behind as lees or lost in the line; the receiving vessel gets the volume less the loss.'])
    . form_input($p, 'transferred_at', 'Transferred at', $input['transferred_at'] ?? '', $errors, ['type' => 'datetime-local', 'icon' => 'feather-clock', 'required' => true])
    . form_textarea($p, 'note', 'Note', $input['note'] ?? '', $errors, ['last' => true]);
echo view('batches/partials/event-form.php', ['batch' => $batch, 'formId' => $p, 'action' => '/batches/' . (int) $batch['id'] . '/transfer', 'title' => 'Transfer',
    'saveLabel' => 'Save Transfer', 'intro' => '', 'bodyHtml' => $body, 'errors' => $errors]);
