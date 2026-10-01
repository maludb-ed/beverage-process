<?php
/**
 * Shared report wrapper: header (filters + export button) and the results region.
 * @var string $title  @var string $screen  @var string $filtersHtml  @var string $exportHtml  @var string $resultsHtml
 */
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => $screen, 'crumbs' => ['Reports' => null, $title => null], 'actionsHtml' => $filtersHtml . $exportHtml]) ?>
<div class="main-content" id="<?= e($screen) ?>-content">
    <div class="row">
        <?= $resultsHtml ?>
    </div>
</div>
