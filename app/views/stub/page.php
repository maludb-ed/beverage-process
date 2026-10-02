<?php /** @var array $item */ ?>
<?= view('shared/page-header.php', ['title' => $item['label'], 'screen' => $item['screen'], 'crumbs' => [$item['group'] => null, $item['label'] => null]]) ?>
<div class="main-content" id="<?= e($item['screen']) ?>-content">
    <div class="row"><div class="col-lg-12"><div class="card"><div class="card-body text-center py-5">
        <i class="feather-layers fs-1 mb-4"></i>
        <p class="text-muted mb-1"><?= e($item['label']) ?> arrives with build slice <?= e($item['slice']) ?>.</p>
        <p class="fs-12 text-muted">The navigation, URL and command-bar entry already exist so the finished application feels the same as this shell.</p>
    </div></div></div></div>
</div>
