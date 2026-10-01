<?php /** @var array $vessels  @var array $plans  @var array $occupants  @var string $from  @var string $to  @var string $today */
$actions = '<div class="input-group"><span class="input-group-text">From</span><input type="date" class="form-control" name="from" id="production-calendar-filter-from" value="' . e($from) . '" hx-get="/production-orders/calendar" hx-trigger="change" hx-target="#production-calendar-grid" hx-swap="outerHTML" hx-include="#production-calendar-filter-to" aria-label="From date" /></div>'
    . '<div class="input-group"><span class="input-group-text">To</span><input type="date" class="form-control" name="to" id="production-calendar-filter-to" value="' . e($to) . '" hx-get="/production-orders/calendar" hx-trigger="change" hx-target="#production-calendar-grid" hx-swap="outerHTML" hx-include="#production-calendar-filter-from" aria-label="To date" /></div>'
    . nav_button('production-calendar-orders-btn', '/production-orders/', 'Orders', 'feather-list', 'btn btn-light-brand');
?>
<?= view('shared/page-header.php', ['title' => 'Vessel calendar', 'screen' => 'production-calendar', 'crumbs' => ['Production' => null, 'Production orders' => '/production-orders/', 'Vessel calendar' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="production-calendar-content">
    <div class="row">
        <?= view('production-orders/partials/calendar.php', ['vessels' => $vessels, 'plans' => $plans, 'occupants' => $occupants, 'from' => $from, 'to' => $to, 'today' => $today]) ?>
    </div>
</div>
