<?php
/** @var int|string $n  @var array $stage  @var array $stageOptions  @var array $rowErrors */
$p = 'recipe-stage-row-' . $n;
$rowErrors = $rowErrors ?? [];
$field = static fn(string $name) => 'stages[' . $n . '][' . $name . ']';
$err = static fn(string $name) => isset($rowErrors[$name]) ? '<div class="invalid-feedback d-block">' . e($rowErrors[$name]) . '</div>' : '';
$inv = static fn(string $name) => isset($rowErrors[$name]) ? ' is-invalid' : '';
?>
<div class="recipe-stage border rounded p-3 mb-3" id="<?= e($p) ?>">
    <div class="row g-3 align-items-end">
        <div class="col-4 col-md-1">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-seq">Seq</label>
            <input type="number" min="1" step="1" class="form-control recipe-stage-seq<?= $inv('seq') ?>" id="<?= e($p) ?>-seq" name="<?= e($field('seq')) ?>" value="<?= e($stage['seq'] ?? '') ?>" /><?= $err('seq') ?>
        </div>
        <div class="col-8 col-md-3">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-stage">Stage</label>
            <select class="form-select recipe-stage-code<?= $inv('stage_code') ?>" id="<?= e($p) ?>-stage" name="<?= e($field('stage_code')) ?>">
                <option value="">Choose a stage</option>
                <?php foreach ($stageOptions as $code => $name): ?><option value="<?= e($code) ?>"<?= ($stage['stage_code'] ?? '') === (string) $code ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
            </select><?= $err('stage_code') ?>
        </div>
        <div class="col-6 col-md-2">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-loss">Expected loss (%)</label>
            <input type="number" min="0" max="100" step="0.01" class="form-control<?= $inv('loss') ?>" id="<?= e($p) ?>-loss" name="<?= e($field('loss')) ?>" value="<?= e($stage['expected_loss_pct'] ?? '0') ?>" /><?= $err('loss') ?>
        </div>
        <div class="col-6 col-md-2">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-days">Duration (days)</label>
            <input type="number" min="0" step="1" class="form-control<?= $inv('days') ?>" id="<?= e($p) ?>-days" name="<?= e($field('days')) ?>" value="<?= e($stage['expected_duration_days'] ?? '') ?>" /><?= $err('days') ?>
        </div>
        <div class="col-12 col-md-3">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-instructions">Instructions</label>
            <input type="text" maxlength="1000" class="form-control" id="<?= e($p) ?>-instructions" name="<?= e($field('instructions')) ?>" value="<?= e($stage['instructions'] ?? '') ?>" />
        </div>
        <div class="col-12 col-md-1 text-end">
            <button type="button" class="btn btn-sm btn-light-brand" id="<?= e($p) ?>-remove-btn" hx-on:click="this.closest('.recipe-stage').remove()"><i class="feather-trash-2"></i><span class="visually-hidden">Remove stage</span></button>
        </div>
    </div>
</div>
