<?php
/** @var int|string $n  @var array $line  @var array $catalog  @var array $stageCodes  @var array $stageNames  @var array $lineErrors */
$p = 'recipe-line-row-' . $n;
$lineErrors = $lineErrors ?? [];
$item = $catalog[(int) ($line['item_id'] ?? 0)] ?? null;
$basis = $line['basis'] ?? 'per_volume';
$field = static fn(string $name) => 'lines[' . $n . '][' . $name . ']';
$err = static fn(string $name) => isset($lineErrors[$name]) ? '<div class="invalid-feedback d-block">' . e($lineErrors[$name]) . '</div>' : '';
$inv = static fn(string $name) => isset($lineErrors[$name]) ? ' is-invalid' : '';
$stageOptions = [];
foreach ($stageCodes as $code) { $stageOptions[$code] = $stageNames[$code] ?? $code; }
if (($line['stage_code'] ?? '') !== '' && !isset($stageOptions[$line['stage_code']])) { $stageOptions[$line['stage_code']] = $stageNames[$line['stage_code']] ?? $line['stage_code']; }
$refresh = 'hx-get="/recipes/line-row" hx-trigger="change" hx-target="#' . e($p) . '" hx-swap="outerHTML" hx-include="#' . e($p) . ', .recipe-stage-code"';
?>
<div class="recipe-line border rounded p-3 mb-3" id="<?= e($p) ?>">
    <div class="row g-3 align-items-end">
        <div class="col-4 col-md-1">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-seq">Seq</label>
            <input type="number" min="1" step="1" class="form-control recipe-line-seq<?= $inv('seq') ?>" id="<?= e($p) ?>-seq" name="<?= e($field('seq')) ?>" value="<?= e($line['seq'] ?? '') ?>" /><?= $err('seq') ?>
        </div>
        <div class="col-8 col-md-5">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-item">Item</label>
            <select class="form-select<?= $inv('item') ?>" id="<?= e($p) ?>-item" name="<?= e($field('item_id')) ?>" <?= $refresh ?> hx-vals='{"n": "<?= e($n) ?>", "item_changed": "1"}'>
                <option value="">Choose an item</option>
                <?php foreach ($catalog as $itemId => $option): ?>
                    <option value="<?= e($itemId) ?>"<?= (int) ($line['item_id'] ?? 0) === $itemId ? ' selected' : '' ?>><?= e($option['code'] . ' — ' . $option['name']) ?></option>
                <?php endforeach; ?>
            </select><?= $err('item') ?>
        </div>
        <div class="col-6 col-md-3">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-stage">Stage</label>
            <select class="form-select<?= $inv('stage') ?>" id="<?= e($p) ?>-stage" name="<?= e($field('stage_code')) ?>">
                <option value="">Choose a stage</option>
                <?php foreach ($stageOptions as $code => $name): ?><option value="<?= e($code) ?>"<?= ($line['stage_code'] ?? '') === (string) $code ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
            </select><?= $err('stage') ?>
        </div>
        <div class="col-6 col-md-3">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-purpose">Purpose</label>
            <select class="form-select<?= $inv('purpose') ?>" id="<?= e($p) ?>-purpose" name="<?= e($field('purpose')) ?>">
                <?php foreach (RECIPE_PURPOSES as $code => $label): ?><option value="<?= e($code) ?>"<?= ($line['purpose'] ?? 'other') === $code ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select><?= $err('purpose') ?>
        </div>
    </div>
    <div class="row g-3 align-items-end mt-0">
        <div class="col-12 col-md-3">
            <span class="fw-semibold fs-12 d-block" id="<?= e($p) ?>-basis-label">Basis</span>
            <div id="<?= e($p) ?>-basis" role="radiogroup" aria-labelledby="<?= e($p) ?>-basis-label">
                <?php foreach (RECIPE_BASES as $code => $label): ?>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="<?= e($field('basis')) ?>" id="<?= e($p) ?>-basis-<?= e(str_replace('_', '-', $code)) ?>" value="<?= e($code) ?>"<?= $basis === $code ? ' checked' : '' ?> <?= $refresh ?> hx-vals='{"n": "<?= e($n) ?>"}' />
                        <label class="form-check-label" for="<?= e($p) ?>-basis-<?= e(str_replace('_', '-', $code)) ?>"><?= e($code === 'per_volume' ? 'Per ' . display_unit('L') : 'Fixed') ?></label>
                    </div>
                <?php endforeach; ?>
            </div>
            <?= $err('basis') ?>
        </div>
        <div class="col-6 col-md-3">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-qty">Quantity</label>
            <div class="input-group">
                <input type="number" min="0" step="any" class="form-control<?= $inv('qty') ?>" id="<?= e($p) ?>-qty" name="<?= e($field('qty')) ?>" value="<?= e($line['qty'] ?? '') ?>" />
                <div class="input-group-text" id="<?= e($p) ?>-unit"><?= e(recipe_qty_unit_label($item, $basis)) ?></div>
            </div><?= $err('qty') ?>
        </div>
        <div class="col-6 col-md-2">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-mode">Consumption</label>
            <select class="form-select<?= $inv('mode') ?>" id="<?= e($p) ?>-mode" name="<?= e($field('mode')) ?>">
                <?php foreach (RECIPE_MODES as $code => $label): ?><option value="<?= e($code) ?>"<?= ($line['consumption_mode'] ?? 'explicit') === $code ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
            </select><?= $err('mode') ?>
        </div>
        <div class="col-8 col-md-3">
            <label class="fw-semibold fs-12" for="<?= e($p) ?>-notes">Notes</label>
            <input type="text" maxlength="500" class="form-control" id="<?= e($p) ?>-notes" name="<?= e($field('notes')) ?>" value="<?= e($line['notes'] ?? '') ?>" />
        </div>
        <div class="col-4 col-md-1 text-end">
            <button type="button" class="btn btn-sm btn-light-brand" id="<?= e($p) ?>-remove-btn" hx-on:click="this.closest('.recipe-line').remove()"><i class="feather-trash-2"></i><span class="visually-hidden">Remove line</span></button>
        </div>
    </div>
</div>
