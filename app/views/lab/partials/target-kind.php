<?php /** @var string $prefix  @var string $kind */
$id = field_id($prefix, 'target_kind');
?>
<?= form_row_open($prefix, 'target_kind', 'Reading for') ?>
    <div id="<?= e($id) ?>" class="d-flex gap-4">
        <?php foreach (['batch' => 'Batch', 'lot' => 'Lot'] as $value => $label): ?>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="target_kind" value="<?= e($value) ?>" id="<?= e($id) ?>-<?= e($value) ?>"<?= $kind === $value ? ' checked' : '' ?>
                       hx-get="/lab/options?prefix=<?= e(rawurlencode($prefix)) ?>" hx-trigger="change" hx-target="#<?= e($prefix) ?>-target-block" hx-swap="outerHTML" />
                <label class="form-check-label" for="<?= e($id) ?>-<?= e($value) ?>"><?= e($label) ?></label>
            </div>
        <?php endforeach; ?>
    </div>
<?= form_row_close() ?>
