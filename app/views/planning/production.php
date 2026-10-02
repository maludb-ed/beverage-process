<?php /** @var array $p  @var array $user  @var bool $canPrice */
$s = 'planning-production';
$canCreate = user_can($user, 'production');
$actions = view('planning/partials/levels.php', ['screen' => $s, 'url' => '/planning/production', 'level' => $p['level']]);
?>
<?= view('shared/page-header.php', ['title' => 'Suggested production', 'screen' => $s, 'crumbs' => ['Planning' => '/planning/', 'Suggested production' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="<?= $s ?>-content">
    <div class="row"><div class="col-lg-12">
        <?= view('planning/partials/warnings.php', ['warnings' => $p['warnings'], 'screen' => $s]) ?>
        <div class="card" id="<?= $s ?>-suggestions-card">
            <div class="card-header"><h5 class="card-title">Batches to start</h5><span class="fs-12 text-muted">Rounded up to the active recipe's batch size; pitch by = needed by − the recipe's stage durations.</span></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="<?= $s ?>-table">
                    <thead class="thead-light"><tr><th>Product</th><th>Batches</th><th>Volume</th><th>Short</th><th>Needed by</th><th>Pitch by</th><th>Driven by</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($p['production'] as $i => $x): $name = $p['products'][$x['product_id']] ?? ''; ?>
                        <tr id="<?= $s ?>-row-<?= e($i) ?>">
                            <td><?= e($name) ?></td>
                            <td><?= e($x['batches']) ?></td>
                            <td><?= fmt_qty_html($x['volume_l'], 'L') ?></td>
                            <td><?= fmt_qty_html($x['shortfall_l'], 'L') ?></td>
                            <td><?= e(format_date($x['needed_by'])) ?></td>
                            <td class="<?= $x['late'] ? 'text-danger fw-semibold' : '' ?>"><?= e(format_date($x['pitch_by'])) ?><?= $x['late'] ? ' <small>(late)</small>' : '' ?><div class="fs-11 text-muted fw-normal"><?= e($x['lead_days']) ?> days to ready<?= $x['missing_durations'] ? ', ' . e($x['missing_durations']) . ' stages without a duration' : '' ?></div></td>
                            <td><?= view('planning/partials/driver.php', ['driver' => $x['driver']]) ?></td>
                            <td class="text-end">
                                <?php if ($canCreate): ?><?= nav_button($s . '-row-' . $i . '-create-btn', '/production-orders/new?product_id=' . (int) $x['product_id'] . '&volume_gal=' . rawurlencode((string) round((float) to_display($x['volume_l'], 'L'), 1)) . '&pitch_on=' . rawurlencode(max($x['pitch_by'], today())), 'Create production order', 'feather-plus', 'btn btn-sm btn-light-brand') ?><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($p['production'] === []): ?><tr id="<?= $s ?>-empty"><td colspan="8" class="text-center text-muted py-4">No batches needed: bulk on hand and planned covers the next <?= e(count($p['weeks'])) ?> weeks.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card" id="<?= $s ?>-supply-card">
            <div class="card-header"><h5 class="card-title">Bulk counted as supply</h5><span class="fs-12 text-muted">Active batches after draft packaging runs and remaining recipe losses, and production orders not yet started.</span></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="<?= $s ?>-supply-table">
                    <thead class="thead-light"><tr><th>Source</th><th>Product</th><th>Volume</th><th>Ready</th></tr></thead>
                    <tbody>
                    <?php foreach ($p['bulk_supply_rows'] as $i => $b): $url = $b['kind'] === 'batch' ? '/batches/' . $b['id'] : '/production-orders/' . $b['id']; ?>
                        <tr id="<?= $s ?>-supply-<?= e($i) ?>">
                            <td><a <?= nav_attrs($url) ?>><?= e($b['ref']) ?></a> <small class="text-muted"><?= $b['kind'] === 'batch' ? 'batch' : 'production order' ?></small></td>
                            <td><?= e($p['products'][$b['product_id']] ?? '') ?></td>
                            <td><?= fmt_qty_html($b['volume_l'], 'L') ?></td>
                            <td><?= e(format_date($b['ready_on'])) ?><?= $b['week'] === null ? ' <small class="text-muted">(after the horizon)</small>' : '' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($p['bulk_supply_rows'] === []): ?><tr><td colspan="4" class="text-center text-muted py-4">No active batches or planned production.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div></div>
</div>
