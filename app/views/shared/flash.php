<?php /** @var string $kind  @var string $message */ $color = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'][$kind] ?? 'info'; ?>
<div class="alert alert-<?= e($color) ?> alert-dismissible mt-3 mx-4" role="alert" id="flash-<?= e($kind) ?>">
    <?= e($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
