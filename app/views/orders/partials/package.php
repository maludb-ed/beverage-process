<?php
/** @var ?array $order  @var ?int $formatId  @var array $plan  @var array $input  @var array $errors */
$cancelUrl = $order ? '/orders/' . (int) $order['id'] : '/orders/to-package';
$crumbs = $order ? ['Sales' => null, 'Customer orders' => '/orders/', $order['number'] => $cancelUrl, 'Package' => null]
                 : ['Sales' => null, 'Packaging queue' => '/orders/to-package', 'Package' => null];
$formErrors = isset($errors['form']) ? [$errors['form']] : [];
?>
<?= view('shared/page-header.php', ['title' => $order ? 'Package ' . $order['number'] : 'Package for orders', 'screen' => 'order-package', 'crumbs' => $crumbs,
    'actionsHtml' => $plan === [] ? '' : form_actions('order-package-form', $cancelUrl, 'Create Packaging Runs')]) ?>
<div class="main-content" id="order-package-content">
    <form id="order-package-form" method="post" action="/orders/package-save" hx-post="/orders/package-save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($order): ?><input type="hidden" name="order_id" value="<?= e($order['id']) ?>" /><?php endif; ?>
        <?php if ($formatId): ?><input type="hidden" name="format" value="<?= e($formatId) ?>" /><?php endif; ?>
        <div class="row"><div class="col-lg-12">
            <?= view('shared/validation-errors.php', ['errors' => $formErrors, 'id' => 'order-package-errors']) ?>
            <?php if ($plan === []): ?>
                <div class="card" id="order-package-empty"><div class="card-body text-muted">Nothing on these orders needs packaging.</div></div>
            <?php endif; ?>
            <?php foreach ($plan as $configId => $group): $p = 'order-package-format-' . (int) $configId; $config = $group['config'];
                $in = $input[$configId] ?? [];
                $units = $in['units'] ?? (string) $group['need'];
                $defaultBatch = $group['default_batch_id'] !== null ? $group['batches'][$group['default_batch_id']] : null;
                $defaultSource = '';
                if ($defaultBatch !== null && $defaultBatch['vessels'] !== []) {
                    $largest = array_reduce($defaultBatch['vessels'], static fn($c, $v) => $c === null || (float) $v['volume_l'] > (float) $c['volume_l'] ? $v : $c);
                    $defaultSource = $defaultBatch['id'] . ':' . $largest['vessel_id'];
                }
                $source = $in['source'] ?? $defaultSource;
                $locations = $defaultBatch['locations'] ?? [];
                foreach ($group['batches'] as $b) { $locations += $b['locations']; }
                $location = (int) ($in['output_location_id'] ?? 0) ?: (array_key_first($locations) ?? 0);
            ?>
            <div class="card" id="<?= e($p) ?>">
                <div class="card-header">
                    <h5 class="card-title"><?= e($config['name']) ?></h5>
                    <span class="fs-12 text-muted" id="<?= e($p) ?>-summary"><?= e(number_format($group['need'])) ?> units to package, about <?= e(fmt_qty($group['volume_l'], 'L')) ?> at <?= e(rtrim(rtrim(number_format((float) $config['expected_loss_pct'], 2), '0'), '.')) ?>% expected loss</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="<?= e($p) ?>-lines-table">
                        <thead class="thead-light"><tr><th>Order</th><th>Customer</th><th>Due</th><th>Open</th><th>In draft runs</th><th>Covered by stock</th><th>To package</th></tr></thead>
                        <tbody>
                        <?php foreach ($group['lines'] as $lineId => $line): ?>
                            <tr id="<?= e($p) ?>-line-<?= e($lineId) ?>">
                                <td><input type="hidden" name="line_ids[]" value="<?= e($lineId) ?>" /><a <?= nav_attrs('/orders/' . (int) $line['sales_order_id']) ?>><?= e($line['order_number']) ?></a></td>
                                <td><?= e($line['customer_name']) ?></td>
                                <td><?= e(format_date($line['requested_on'])) ?></td>
                                <td><?= e(number_format((int) $line['units_open'])) ?></td>
                                <td><?= e(number_format((int) $line['units_in_draft_runs'])) ?></td>
                                <td><?= e(number_format((int) $line['stock_covered'])) ?></td>
                                <td class="fw-semibold"><?= e(number_format((int) $line['need'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-body border-top">
                    <?php if (isset($errors[$configId])): ?><div class="alert alert-danger" id="<?= e($p) ?>-error"><?= e($errors[$configId]) ?></div><?php endif; ?>
                    <?php if ($group['batches'] === []): ?>
                        <div class="alert alert-warning mb-4" id="<?= e($p) ?>-no-bulk">No batch of this product is ready to package. Plan a production order for about <?= e(fmt_qty($group['volume_l'], 'L')) ?>, or leave units at 0.</div>
                        <div class="mb-4"><?= nav_button($p . '-plan-production-btn', '/production-orders/new?product_id=' . (int) $config['product_id'] . '&volume_gal=' . rawurlencode((string) round((float) to_display($group['volume_l'], 'L'), 1)), 'Plan production', 'feather-calendar', 'btn btn-light-brand') ?></div>
                    <?php endif; ?>
                    <div class="row g-3 align-items-end">
                        <div class="col-6 col-md-2">
                            <label class="fw-semibold fs-12" for="<?= e($p) ?>-units">Units</label>
                            <input type="number" min="0" step="1" class="form-control" id="<?= e($p) ?>-units" name="groups[<?= e($configId) ?>][units]" value="<?= e($group['batches'] === [] && !isset($in['units']) ? '0' : $units) ?>" />
                        </div>
                        <div class="col-12 col-md-5">
                            <label class="fw-semibold fs-12" for="<?= e($p) ?>-source">Batch and vessel</label>
                            <select class="form-select" id="<?= e($p) ?>-source" name="groups[<?= e($configId) ?>][source]">
                                <?php if ($group['batches'] === []): ?><option value="">No batch ready</option><?php endif; ?>
                                <?php foreach ($group['batches'] as $batch): foreach ($batch['vessels'] as $vessel): $value = $batch['id'] . ':' . $vessel['vessel_id']; ?>
                                    <option value="<?= e($value) ?>"<?= $source === $value ? ' selected' : '' ?>><?= e($batch['number'] . ' — ' . $vessel['vessel_name'] . ' — ' . fmt_qty($vessel['volume_l'], 'L') . ' — ' . humanize($batch['current_stage_code']) . ($batch['released'] ? ', released' : ', not released')) ?></option>
                                <?php endforeach; endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="fw-semibold fs-12" for="<?= e($p) ?>-location">Output location</label>
                            <select class="form-select" id="<?= e($p) ?>-location" name="groups[<?= e($configId) ?>][output_location_id]">
                                <?php foreach ($locations as $locId => $locName): ?><option value="<?= e($locId) ?>"<?= $location === (int) $locId ? ' selected' : '' ?>><?= e($locName) ?></option><?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="fw-semibold fs-12" for="<?= e($p) ?>-run-on">Run date</label>
                            <input type="date" class="form-control" id="<?= e($p) ?>-run-on" name="groups[<?= e($configId) ?>][run_on]" value="<?= e($in['run_on'] ?? today()) ?>" />
                        </div>
                    </div>
                    <div class="fs-12 text-muted mt-3">Creates a draft run. Units above the orders' need go to stock. Before posting, enter ABV and CO2 on the run and choose lots for any explicitly issued materials.</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div></div>
    </form>
</div>
