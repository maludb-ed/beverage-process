<?php /** @var array $query  @var array $products */ ?>
<form id="report-batch-costs-filters" class="d-flex flex-wrap gap-2 align-items-center" action="/reports/batch-costs" method="get" hx-get="/reports/batch-costs" hx-target="#report-batch-costs-results" hx-swap="outerHTML" hx-trigger="change, keyup changed delay:400ms">
    <select class="form-select" name="product" id="report-batch-costs-filter-product" aria-label="Product">
        <option value="">All products</option>
        <?php foreach ($products as $id => $name): ?><option value="<?= e($id) ?>"<?= (string) $query['product'] === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
    </select>
    <select class="form-select" name="status" id="report-batch-costs-filter-status" aria-label="Status">
        <option value="">All except dumped</option>
        <?php foreach (REPORT_BATCH_STATUSES as $code => $label): ?><option value="<?= e($code) ?>"<?= $query['status'] === $code ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
    </select>
    <input type="date" class="form-control" name="date_from" id="report-batch-costs-filter-date-from" aria-label="Batches started from" title="Batches started from" value="<?= e($query['date_from']) ?>">
    <input type="date" class="form-control" name="date_to" id="report-batch-costs-filter-date-to" aria-label="Batches started to" title="Batches started to" value="<?= e($query['date_to']) ?>">
</form>
