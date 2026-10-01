<?php /** @var string $screen  @var string $name  @var string $url  @var array $options  @var ?string $selected  @var string $allLabel  @var string $include */
$id = $screen . '-filter-' . str_replace('_', '-', $name);
?>
<select class="form-select" name="<?= e($name) ?>" id="<?= e($id) ?>" hx-get="<?= e($url) ?>" hx-target="#<?= e($screen) ?>-results" hx-swap="outerHTML" hx-trigger="change" hx-include="<?= e($include) ?>">
    <option value=""><?= e($allLabel) ?></option>
    <?php foreach ($options as $value => $label): ?>
        <option value="<?= e($value) ?>"<?= (string) $selected === (string) $value ? ' selected' : '' ?>><?= e($label) ?></option>
    <?php endforeach; ?>
</select>
