<?php /** @var string $prefix  @var string $kind  @var array $input  @var array $errors  @var array $targets  @var array $stages  @var string $stageDefault */
$optionsUrl = '/lab/options?prefix=' . rawurlencode($prefix);
$hx = ' hx-get="' . e($optionsUrl) . '" hx-trigger="change" hx-target="#' . e($prefix) . '-target-block" hx-swap="outerHTML" hx-include="input[name=target_kind]:checked"';
?>
<div id="<?= e($prefix) ?>-target-block">
    <?= form_select($prefix, 'target_id', $kind === 'lot' ? 'Lot' : 'Batch', $targets, $input['target_id'] ?? '', $errors,
        ['required' => true, 'blank' => 'Choose…', 'name' => 'target_id', 'extra' => $prefix === 'lab-reading-form' && $kind === 'batch' ? $hx : '']) ?>
    <?php if ($prefix === 'lab-reading-form' && $kind === 'batch'): ?>
        <?= form_select($prefix, 'stage_code', 'Stage', $stages, $stageDefault, $errors, ['blank' => 'Current stage']) ?>
    <?php endif; ?>
</div>
