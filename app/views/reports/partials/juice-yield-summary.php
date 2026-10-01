<?php
/** @var array $summary */
$tonClass = static function ($v): string {
    if ($v === null) { return ''; }
    return (float) $v < 130 ? 'text-warning' : ((float) $v > 185 ? 'text-info' : 'text-success');
};
?>
<div class="col-lg-12" id="report-juice-yield-summary-card">
    <div class="card stretch stretch-full">
        <div class="card-header"><h5 class="card-title">Yield by variety</h5></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="report-juice-yield-summary-table">
                    <thead class="thead-light">
                        <tr>
                            <th id="report-juice-yield-summary-col-variety">Variety</th>
                            <th id="report-juice-yield-summary-col-runs">Press runs</th>
                            <th id="report-juice-yield-summary-col-fruit">Fruit</th>
                            <th id="report-juice-yield-summary-col-juice">Juice</th>
                            <th id="report-juice-yield-summary-col-gal-per-ton">Gal / ton</th>
                            <th id="report-juice-yield-summary-col-gal-per-bushel">Gal / bushel</th>
                        </tr>
                    </thead>
                    <tbody id="report-juice-yield-summary-tbody">
                        <?php if ($summary === []): ?>
                            <tr id="report-juice-yield-summary-empty"><td colspan="6" class="text-center text-muted py-5"><i class="feather-inbox fs-1 d-block mb-3"></i>No posted press runs in this season.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($summary as $i => $row): $rid = 'report-juice-yield-summary-row-' . $i; ?>
                            <tr id="<?= e($rid) ?>">
                                <td id="<?= e($rid) ?>-variety"><?= $row['variety'] === null ? '<span class="text-muted">Unspecified</span>' : e($row['variety']) ?></td>
                                <td id="<?= e($rid) ?>-runs"><?= e((int) $row['press_runs']) ?></td>
                                <td id="<?= e($rid) ?>-fruit"><?= fmt_qty_html($row['fruit_kg'], 'kg', 0, 'fruit') ?> <small class="text-muted">(<?= e(number_format((float) $row['tons'], 2)) ?> tons)</small></td>
                                <td id="<?= e($rid) ?>-juice"><?= fmt_qty_html($row['juice_l'], 'L', 0) ?></td>
                                <td id="<?= e($rid) ?>-gal-per-ton" class="<?= $tonClass($row['gal_per_ton']) ?>"><?= $row['gal_per_ton'] === null ? '' : e(number_format((float) $row['gal_per_ton'], 1)) ?></td>
                                <td id="<?= e($rid) ?>-gal-per-bushel"><?= $row['gal_per_bushel'] === null ? '' : e(number_format((float) $row['gal_per_bushel'], 2)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer fs-12 text-muted" id="report-juice-yield-benchmark">Benchmark: 130 to 185 gal per ton, 2.5 to 3.5 gal per bushel.</div>
    </div>
</div>
