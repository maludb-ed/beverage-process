<?php /** @var array $locations  @var array $run  @var array $errors  @var ?array $batch */
$p = 'packaging-run-form';
$help = $batch === null ? 'Choose a batch first.' : ($locations === [] ? 'No packaged goods location exists for this premises. Add one under Locations.' : null);
?>
<?= form_select($p, 'output_location_id', 'Output location', $locations, $run['output_location_id'] ?? '', $errors, ['required' => true, 'blank' => 'Choose a location', 'help' => $help]) ?>
