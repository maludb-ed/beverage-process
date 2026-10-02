<?php /** @var array $p  @var array $user  @var bool $canPrice */
$s = 'planning';
$fmt = static fn(float $n) => $n == 0 ? '<span class="text-muted">0</span>' : e(rtrim(rtrim(number_format($n, 1), '0'), '.'));
$configs = array_keys($p['packaging']);
$products = array_keys($p['bulk']);
$actions = view('planning/partials/levels.php', ['screen' => $s, 'url' => '/planning/', 'level' => $p['level']]);
$production = $p['production'];
$purchasing = $p['purchasing'];
$firstPitch = $production === [] ? null : $production[0];
$firstBuy = $purchasing === [] ? null : $purchasing[array_key_first($purchasing)];
?>
<?= view('shared/page-header.php', ['title' => 'Projections', 'screen' => $s, 'crumbs' => ['Planning' => null, 'Projections' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="<?= $s ?>-content">
    <div class="row">
        <div class="col-md-6">
            <div class="card" id="<?= $s ?>-production-tile">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3"><h5 class="fw-bold mb-0">Batches to start</h5><?= nav_button($s . '-production-btn', '/planning/production?demand=' . $p['level'], 'View', 'feather-arrow-right', 'btn btn-sm btn-light-brand') ?></div>
                    <div class="fs-3 fw-bold" id="<?= $s ?>-production-count"><?= e(array_sum(array_column($production, 'batches'))) ?></div>
                    <div class="fs-12 text-muted" id="<?= $s ?>-production-next"><?= $firstPitch ? 'First pitch by ' . e(format_date($firstPitch['pitch_by'])) . ($firstPitch['late'] ? ' <span class="text-danger fw-semibold">(late)</span>' : '') : 'Bulk on hand and planned covers the next ' . count($p['weeks']) . ' weeks.' ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card" id="<?= $s ?>-purchasing-tile">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3"><h5 class="fw-bold mb-0">Items to buy</h5><?= nav_button($s . '-purchasing-btn', '/planning/purchasing?demand=' . $p['level'], 'View', 'feather-arrow-right', 'btn btn-sm btn-light-brand') ?></div>
                    <div class="fs-3 fw-bold" id="<?= $s ?>-purchasing-count"><?= e(count($purchasing)) ?></div>
                    <div class="fs-12 text-muted" id="<?= $s ?>-purchasing-next"><?= $firstBuy ? e($firstBuy['item']['code']) . ' first, needed by ' . e(format_date($firstBuy['needed_by'])) . ($firstBuy['late'] ? ' <span class="text-danger fw-semibold">(order now)</span>' : '') : 'Stock and open purchase orders cover the next ' . count($p['weeks']) . ' weeks.' ?></div>
                </div>
            </div>
        </div>
        <div class="col-lg-12">
            <?= view('planning/partials/warnings.php', ['warnings' => $p['warnings'], 'screen' => $s]) ?>
            <div class="card" id="<?= $s ?>-weeks-card">
                <div class="card-header"><h5 class="card-title">Week by week</h5></div>
                <p class="fs-12 text-muted px-4 pt-3 mb-0" id="<?= $s ?>-weeks-note">Counting: <?= e(PLANNING_LEVELS[$p['level']]) ?>. Units to package after stock and draft runs; bulk short before suggested batches.</p>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="<?= $s ?>-weeks-table">
                        <thead class="thead-light"><tr>
                            <th>Week of</th><th>Firm</th><th>Standing</th><th>Forecast</th><?php if ($canPrice): ?><th class="text-end">Value</th><?php endif; ?>
                            <?php foreach ($configs as $c): ?><th>Package: <?= e($p['configs'][$c]['name']) ?></th><?php endforeach; ?>
                            <?php foreach ($products as $prod): ?><th>Bulk short: <?= e($p['products'][$prod] ?? $prod) ?></th><?php endforeach; ?>
                        </tr></thead>
                        <tbody>
                        <?php foreach ($p['weeks'] as $w => $week): $value = $p['value_by_type']['firm'][$w] + $p['value_by_type']['standing'][$w] + $p['value_by_type']['forecast'][$w]; ?>
                            <tr id="<?= $s ?>-week-<?= e($week) ?>">
                                <td class="text-nowrap"><?= e(format_date($week)) ?></td>
                                <td><?= $fmt($p['demand_by_type']['firm'][$w]) ?></td>
                                <td><?= $fmt($p['demand_by_type']['standing'][$w]) ?></td>
                                <td><?= $fmt($p['demand_by_type']['forecast'][$w]) ?></td>
                                <?php if ($canPrice): ?><td class="text-end"><?= $value > 0 ? '$' . e(number_format($value, 2)) : '<span class="text-muted">—</span>' ?></td><?php endif; ?>
                                <?php foreach ($configs as $c): ?><td><?= $fmt($p['packaging'][$c]['to_package'][$w]) ?></td><?php endforeach; ?>
                                <?php foreach ($products as $prod): $short = $p['bulk'][$prod]['short'][$w]; ?><td class="<?= $short > 0 ? 'text-danger fw-semibold' : '' ?>"><?= $short > 0 ? e(fmt_qty($short, 'L')) : '<span class="text-muted">0</span>' ?></td><?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
