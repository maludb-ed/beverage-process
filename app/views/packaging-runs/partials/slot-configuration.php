<?php /** @var array $configurations  @var array $run  @var array $errors  @var ?array $batch */
$p = 'packaging-run-form';
$help = $batch === null ? 'Choose a batch first.' : ($configurations === [] ? 'This product has no active packaging configuration.' : null);
?>
<?= form_select($p, 'packaging_configuration_id', 'Package', $configurations, $run['packaging_configuration_id'] ?? '', $errors, [
    'required' => true, 'blank' => 'Choose a package', 'help' => $help,
    'extra' => ' hx-get="/packaging-runs/options?part=materials" hx-trigger="change" hx-swap="none" hx-include="#packaging-run-form-field-batch-id, #packaging-run-form-field-units-out"',
]) ?>
