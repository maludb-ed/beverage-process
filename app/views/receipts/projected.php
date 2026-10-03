<?php /** @var array $byDate  @var array $overdue  @var array $undated  @var string $month  @var string $today */
$actions = '<div class="input-group"><span class="input-group-text">Month</span><input type="month" class="form-control" name="month" id="receipts-projected-filter-month" value="' . e($month) . '" hx-get="/receipts/projected" hx-trigger="change" hx-target="#receipts-projected-grid" hx-swap="outerHTML" aria-label="Month" /></div>'
    . nav_button('receipts-projected-orders-btn', '/purchase-orders/', 'Purchase orders', 'feather-list', 'btn btn-light-brand');
?>
<?= view('shared/page-header.php', ['title' => 'Projected receipts', 'screen' => 'receipts-projected', 'crumbs' => ['Receiving' => null, 'Projected' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="receipts-projected-content">
    <div class="row">
        <?= view('receipts/partials/projected.php', ['byDate' => $byDate, 'overdue' => $overdue, 'undated' => $undated, 'month' => $month, 'today' => $today]) ?>
    </div>
</div>
