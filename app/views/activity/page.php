<?php /** @var array $rows  @var int $page  @var bool $hasMore  @var string $q */
$actions = '<div class="input-group"><span class="input-group-text"><i class="feather-search"></i></span>'
    . '<input type="search" name="q" id="activity-list-search" class="form-control" placeholder="Search actions, people, records" value="' . e($q) . '" hx-get="/activity/" hx-target="#activity-list-results" hx-swap="outerHTML" hx-trigger="keyup changed delay:400ms, search" /></div>';
?>
<?= view('shared/page-header.php', ['title' => 'Activity', 'screen' => 'activity-list', 'crumbs' => ['Activity' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="activity-list-content">
    <div class="row">
        <?= view('activity/partials/table.php', ['rows' => $rows, 'page' => $page, 'hasMore' => $hasMore, 'q' => $q]) ?>
    </div>
</div>
