<?php /** @var array $units */ ?>
<?= view('shared/page-header.php', ['title' => 'Units', 'screen' => 'units-list', 'crumbs' => ['Setup' => null, 'Units' => null], 'actionsHtml' => '']) ?>
<div class="main-content" id="units-list-content">
    <div class="row">
        <?= view('units/partials/table.php', ['units' => $units]) ?>
    </div>
</div>
