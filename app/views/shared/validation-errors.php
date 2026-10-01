<?php /** @var array $errors  @var string $id */ if ($errors !== []): ?>
<div class="alert alert-danger" role="alert" id="<?= e($id ?? 'form-errors') ?>">
    <ul class="mb-0 ps-3">
        <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
