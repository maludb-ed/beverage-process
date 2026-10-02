<?php /** @var array $toOptions  @var mixed $selected  @var array $errors  @var bool $hasFrom */
echo form_select('transfer-form', 'to_location', 'To', $toOptions, $selected, $errors, [
    'name' => 'to_location_id', 'required' => true,
    'blank' => $hasFrom ? 'Choose a destination' : 'Choose a source first',
    'help' => 'Racks are listed under their area, e.g. "Warehouse · Rack 7". Only locations with the same tax state as the source are offered.',
]);
