<?php /** @var array $batch  @var array $input  @var array $errors  @var array $stageOptions */
$p = 'batch-stage-form';
$unit = display_unit('L');
$body = form_select($p, 'to_stage', 'Move to', $stageOptions, $input['to_stage'] ?? '', $errors, ['required' => true, 'blank' => $stageOptions === [] ? 'No later stage (packaging is a separate step)' : 'Choose the next stage'])
    . form_input($p, 'moved_at', 'Moved at', $input['moved_at'] ?? '', $errors, ['type' => 'datetime-local', 'icon' => 'feather-clock', 'required' => true])
    . form_input($p, 'volume_out_gal', 'Volume out', $input['volume_out_gal'] ?? '', $errors, ['type' => 'number', 'step' => 'any', 'min' => '0', 'icon' => 'feather-droplet', 'suffix' => $unit,
        'help' => 'What leaves ' . $batch['stage_name'] . '; anything less than ' . fmt_qty($batch['current_volume_l'], 'L') . ' is recorded as an expected loss.'])
    . form_textarea($p, 'note', 'Note', $input['note'] ?? '', $errors, ['last' => true]);
echo view('batches/partials/event-form.php', ['batch' => $batch, 'formId' => $p, 'action' => '/batches/' . (int) $batch['id'] . '/stage', 'title' => 'Move stage',
    'saveLabel' => 'Move Stage', 'intro' => '', 'bodyHtml' => $body, 'errors' => $errors]);
