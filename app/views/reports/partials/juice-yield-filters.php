<?php /** @var array $query  @var array $seasons  @var array $varieties  @var array $premises */ ?>
<form id="report-juice-yield-filters" class="d-flex flex-wrap gap-2 align-items-center" action="/reports/juice-yield" method="get" hx-get="/reports/juice-yield" hx-target="#report-juice-yield-results" hx-swap="outerHTML" hx-trigger="change, keyup changed delay:400ms">
    <select class="form-select" name="season_year" id="report-juice-yield-filter-season-year" aria-label="Season">
        <?php foreach ($seasons as $year): ?><option value="<?= e($year) ?>"<?= (int) $query['season_year'] === $year ? ' selected' : '' ?>><?= e($year) ?></option><?php endforeach; ?>
    </select>
    <select class="form-select" name="variety" id="report-juice-yield-filter-variety" aria-label="Variety">
        <option value="">All varieties</option>
        <?php foreach ($varieties as $variety): ?><option value="<?= e($variety) ?>"<?= $query['variety'] === $variety ? ' selected' : '' ?>><?= e($variety) ?></option><?php endforeach; ?>
    </select>
    <?php if (count($premises) > 1): ?>
    <select class="form-select" name="premises_id" id="report-juice-yield-filter-premises" aria-label="Premises">
        <option value="">All premises</option>
        <?php foreach ($premises as $id => $name): ?><option value="<?= e($id) ?>"<?= (string) $query['premises_id'] === (string) $id ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
    </select>
    <?php endif; ?>
</form>
