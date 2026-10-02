<?php /** @var array $warnings  @var string $screen */ if ($warnings !== []): ?>
<div class="card" id="<?= e($screen) ?>-warnings">
    <div class="card-header"><h5 class="card-title">Check the planning data</h5><span class="fs-12 text-muted"><?= e(count($warnings)) ?> notes</span></div>
    <div class="card-body"><ul class="mb-0 fs-12 ps-3">
        <?php foreach ($warnings as $i => $w): ?><li class="mb-1" id="<?= e($screen) ?>-warning-<?= e($i) ?>"><?= e($w) ?></li><?php endforeach; ?>
    </ul></div>
</div>
<?php endif; ?>
