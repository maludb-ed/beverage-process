<?php /** @var array $batch  @var array $input  @var array $errors  @var array $yeastItems  @var array $locations */
$p = 'yeast-harvest-form';
$body = form_select($p, 'yeast_item', 'Yeast item', array_map(static fn($i) => $i['code'] . ' — ' . $i['name'] . ' (' . $i['base_unit_code'] . ')', $yeastItems), $input['yeast_item_id'] ?? '', $errors, ['name' => 'yeast_item_id', 'required' => true, 'blank' => 'Choose a yeast item', 'help' => 'Slurry is measured in liters; choose an item whose base unit is L.'])
    . form_input($p, 'generation', 'Generation', $input['generation'] ?? '1', $errors, ['type' => 'number', 'step' => '1', 'min' => '1', 'required' => true, 'icon' => 'feather-repeat'])
    . form_input($p, 'volume_l', 'Slurry volume', $input['volume_l'] ?? '', $errors, ['type' => 'number', 'step' => 'any', 'min' => '0', 'required' => true, 'icon' => 'feather-droplet', 'suffix' => 'L'])
    . form_input($p, 'cell_count', 'Cell count', $input['cell_count'] ?? '', $errors, ['type' => 'number', 'step' => 'any', 'min' => '0', 'icon' => 'feather-hash', 'suffix' => 'M cells/mL'])
    . form_input($p, 'viability_pct', 'Viability', $input['viability_pct'] ?? '', $errors, ['type' => 'number', 'step' => 'any', 'min' => '0', 'max' => '100', 'icon' => 'feather-percent', 'suffix' => '%'])
    . form_input($p, 'harvested_at', 'Harvested at', $input['harvested_at'] ?? '', $errors, ['type' => 'datetime-local', 'icon' => 'feather-clock', 'required' => true])
    . form_select($p, 'location', 'Store in', $locations, $input['location_id'] ?? (count($locations) === 1 ? array_key_first($locations) : ''), $errors, ['name' => 'location_id', 'required' => true, 'blank' => $locations === [] ? 'No cold room or freezer locations' : 'Choose a location'])
    . form_textarea($p, 'note', 'Note', $input['note'] ?? '', $errors, ['last' => true]);
echo view('batches/partials/event-form.php', ['batch' => $batch, 'formId' => $p, 'action' => '/batches/' . (int) $batch['id'] . '/yeast/save', 'title' => 'Harvest yeast',
    'saveLabel' => 'Save Harvest', 'intro' => 'Creates a released yeast lot with its generation and source batch.', 'bodyHtml' => $body, 'errors' => $errors]);
