<?php /** @var array $query  @var array $customers */ ?>
<form id="report-orders-filters" class="d-flex flex-wrap gap-2 align-items-center" action="/reports/orders" method="get" hx-get="/reports/orders" hx-target="#report-orders-results" hx-swap="outerHTML" hx-trigger="change">
    <input type="date" class="form-control" name="date_from" id="report-orders-filter-date-from" aria-label="Due from" title="Due from" value="<?= e($query['date_from']) ?>">
    <input type="date" class="form-control" name="date_to" id="report-orders-filter-date-to" aria-label="Due to" title="Due to" value="<?= e($query['date_to']) ?>">
    <select class="form-select" name="group_by" id="report-orders-filter-group-by" aria-label="Group by">
        <?php foreach (REPORT_ORDER_GROUP_BY as $code => $label): ?><option value="<?= e($code) ?>"<?= $query['group_by'] === $code ? ' selected' : '' ?>>By <?= e(strtolower($label)) ?></option><?php endforeach; ?>
    </select>
    <select class="form-select" name="customer_id" id="report-orders-filter-customer" aria-label="Customer">
        <option value="">All customers</option>
        <?php foreach ($customers as $id => $name): ?><option value="<?= e($id) ?>"<?= (string) $query['customer_id'] === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
    </select>
</form>
