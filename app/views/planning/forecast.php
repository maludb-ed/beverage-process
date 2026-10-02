<?php /** @var array $grid  @var array $weeks  @var array $rates  @var ?array $last  @var bool $canEdit  @var bool $canPrice */
$s = 'planning-forecast';
$fmt = static fn(float $n) => rtrim(rtrim(number_format($n, 2), '0'), '.');
?>
<?= view('shared/page-header.php', ['title' => 'Forecast', 'screen' => $s, 'crumbs' => ['Planning' => null, 'Forecast' => null], 'actionsHtml' => '']) ?>
<div class="main-content" id="<?= $s ?>-content">
    <div class="row"><div class="col-lg-12">
        <div class="card" id="<?= $s ?>-generate-card">
            <div class="card-body">
                <div class="mb-3"><h5 class="fw-bold mb-0"><span class="d-block mb-2">Demand types</span><span class="fs-12 fw-normal text-muted">
                    <?= badge('Firm', 'success') ?> confirmed orders not yet shipped. <?= badge('Standing', 'warning') ?> dates of standing orders not yet turned into orders.
                    <?= badge('Forecast', 'info') ?> expected orders beyond those; only the part firm and standing demand do not cover in a week counts.</span></h5></div>
                <p class="fs-12 text-muted mb-3" id="<?= $s ?>-last"><?= $last ? 'Run rate last generated ' . e(format_datetime($last['at'])) . ' from ' . e($last['weeks']) . ' weeks of orders.' : 'No run rate generated yet.' ?></p>
                <?php if ($canEdit): ?>
                <form class="row g-3 align-items-end" id="<?= $s ?>-generate-form" hx-post="/planning/forecast/generate" hx-target="#page-content" hx-swap="innerHTML">
                    <div class="col-6 col-md-3"><label class="fw-semibold fs-12" for="<?= $s ?>-history-weeks">Weeks of order history</label><input type="number" min="1" max="104" class="form-control" name="history_weeks" id="<?= $s ?>-history-weeks" value="<?= e(FORECAST_DEFAULT_HISTORY_WEEKS) ?>" /></div>
                    <div class="col-6 col-md-3"><label class="fw-semibold fs-12" for="<?= $s ?>-horizon-weeks">Weeks ahead</label><input type="number" min="1" max="26" class="form-control" name="horizon_weeks" id="<?= $s ?>-horizon-weeks" value="<?= e(FORECAST_DEFAULT_HORIZON_WEEKS) ?>" /></div>
                    <div class="col-12 col-md-6"><button type="submit" class="btn btn-primary" id="<?= $s ?>-generate-btn" hx-confirm="Replace the run-rate forecast from this week on? Manual figures stay."><i class="feather-refresh-cw me-2"></i>Generate from order history</button></div>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php foreach ($grid as $configId => $format): $p = $s . '-format-' . (int) $configId; $rate = $rates[$configId] ?? null; ?>
            <div class="card" id="<?= e($p) ?>">
                <div class="card-header">
                    <h5 class="card-title"><?= e($format['config']['name']) ?> <small class="text-muted fw-normal"><?= e($format['config']['product_name']) ?></small></h5>
                    <span class="fs-12 text-muted" id="<?= e($p) ?>-rate"><?= $rate ? e($fmt($rate['weekly'])) . ' units a week over the last ' . FORECAST_DEFAULT_HISTORY_WEEKS . ' weeks' : 'No orders in the last ' . FORECAST_DEFAULT_HISTORY_WEEKS . ' weeks' ?></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="<?= e($p) ?>-table">
                        <thead class="thead-light"><tr><th>Week of</th><th>Firm</th><th>Standing</th><th>Forecast</th><th>Forecast counted</th><?php if ($canEdit): ?><th class="text-end">Set forecast</th><?php endif; ?></tr></thead>
                        <tbody>
                        <?php foreach ($format['weeks'] as $week => $cell): ?>
                            <tr id="<?= e($p) ?>-week-<?= e($week) ?>">
                                <td><?= e(format_date($week)) ?></td>
                                <td><?= e($fmt($cell['firm'])) ?></td>
                                <td><?= e($fmt($cell['standing'])) ?></td>
                                <td><?= $cell['forecast'] === null ? '<span class="text-muted">—</span>' : e($fmt($cell['forecast'])) . ' ' . badge($cell['method'] === 'manual' ? 'Manual' : 'Run rate', $cell['method'] === 'manual' ? 'primary' : 'secondary') ?></td>
                                <td><?= e($fmt($cell['counted'])) ?></td>
                                <?php if ($canEdit): ?>
                                <td class="text-end">
                                    <form class="d-inline-flex gap-2" id="<?= e($p) ?>-week-<?= e($week) ?>-form" hx-post="/planning/forecast/save" hx-target="#page-content" hx-swap="innerHTML">
                                        <input type="hidden" name="packaging_configuration_id" value="<?= e($configId) ?>" /><input type="hidden" name="week_start" value="<?= e($week) ?>" />
                                        <input type="number" min="0" step="any" class="form-control form-control-sm wd-100" name="units" id="<?= e($p) ?>-week-<?= e($week) ?>-units" value="<?= $cell['method'] === 'manual' ? e($fmt((float) $cell['forecast'])) : '' ?>" placeholder="units" aria-label="Forecast units" />
                                        <button type="submit" class="btn btn-sm btn-light-brand" id="<?= e($p) ?>-week-<?= e($week) ?>-save-btn">Save</button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div></div>
</div>
