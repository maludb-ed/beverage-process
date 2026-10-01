<?php /** @var array $query  @var array $premises */ ?>
<form id="report-valuation-filters" class="d-flex flex-wrap gap-2 align-items-center" action="/reports/valuation" method="get" hx-get="/reports/valuation" hx-target="#report-valuation-results" hx-swap="outerHTML" hx-trigger="change, keyup changed delay:400ms">
    <input type="date" class="form-control" name="as_of" id="report-valuation-filter-as-of" aria-label="As of" title="As of" value="<?= e($query['as_of']) ?>">
    <select class="form-select" name="group_by" id="report-valuation-filter-group-by" aria-label="Group by">
        <?php foreach (REPORT_GROUP_BY as $code => $label): ?><option value="<?= e($code) ?>"<?= $query['group_by'] === $code ? ' selected' : '' ?>>By <?= e(strtolower($label)) ?></option><?php endforeach; ?>
    </select>
    <?php if (count($premises) > 1): ?>
    <select class="form-select" name="premises_id" id="report-valuation-filter-premises" aria-label="Premises">
        <option value="">All premises</option>
        <?php foreach ($premises as $id => $name): ?><option value="<?= e($id) ?>"<?= (string) $query['premises_id'] === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
    </select>
    <?php endif; ?>
</form>
