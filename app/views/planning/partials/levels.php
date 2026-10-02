<?php /** Demand level switcher for the planning screens. @var string $screen  @var string $url  @var string $level */ ?>
<div class="btn-group flex-wrap" role="group" id="<?= e($screen) ?>-levels" aria-label="Demand counted">
    <?php foreach (PLANNING_LEVELS as $key => $label): $href = $url . '?demand=' . $key; ?>
        <a <?= nav_attrs($href) ?> class="btn btn-sm <?= $key === $level ? 'btn-primary' : 'btn-light-brand' ?>" id="<?= e($screen) ?>-level-<?= e($key) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>
