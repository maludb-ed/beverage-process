<?php /** @var array $vessels  @var array $run  @var array $errors  @var ?array $batch */
$p = 'packaging-run-form';
$help = $batch === null ? 'Choose a batch first.' : ($vessels === [] ? 'This batch is not in any vessel.' : null);
?>
<?= form_select($p, 'source_vessel_id', 'Source vessel', $vessels, $run['source_vessel_id'] ?? '', $errors, ['required' => true, 'blank' => 'Choose a vessel', 'help' => $help]) ?>
