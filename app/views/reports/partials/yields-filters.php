<?php /** @var array $query  @var array $products  @var array $stages */ ?>
<form id="report-yields-filters" class="d-flex flex-wrap gap-2 align-items-center" action="/reports/yields" method="get" hx-get="/reports/yields" hx-target="#report-yields-results" hx-swap="outerHTML" hx-trigger="change, keyup changed delay:400ms">
    <select class="form-select" name="product" id="report-yields-filter-product" aria-label="Product">
        <option value="">All products</option>
        <?php foreach ($products as $id => $name): ?><option value="<?= e($id) ?>"<?= (string) $query['product'] === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
    </select>
    <input type="search" class="form-control" name="batch" id="report-yields-filter-batch" placeholder="Batch number" aria-label="Batch number" value="<?= e($query['batch']) ?>">
    <input type="date" class="form-control" name="date_from" id="report-yields-filter-date-from" aria-label="Batches started from" title="Batches started from" value="<?= e($query['date_from']) ?>">
    <input type="date" class="form-control" name="date_to" id="report-yields-filter-date-to" aria-label="Batches started to" title="Batches started to" value="<?= e($query['date_to']) ?>">
    <select class="form-select" name="stage" id="report-yields-filter-stage" aria-label="Stage">
        <option value="">All stages</option>
        <?php foreach ($stages as $code => $name): ?><option value="<?= e($code) ?>"<?= $query['stage'] === (string) $code ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
    </select>
</form>
