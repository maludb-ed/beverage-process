<?php /** @var array $formats  @var bool $canPackage */ ?>
<?= view('shared/page-header.php', ['title' => 'Packaging queue', 'screen' => 'orders-to-package', 'crumbs' => ['Sales' => null, 'Packaging queue' => null], 'actionsHtml' => '']) ?>
<div class="main-content" id="orders-to-package-content">
    <div class="row"><div class="col-lg-12">
        <?php if ($formats === []): ?>
            <div class="card" id="orders-to-package-empty"><div class="card-body text-muted">No confirmed order has units left to package or ship.</div></div>
        <?php endif; ?>
        <?php foreach ($formats as $configId => $format): $p = 'orders-to-package-format-' . (int) $configId; ?>
            <div class="card" id="<?= e($p) ?>">
                <div class="card-header">
                    <h5 class="card-title"><?= e($format['configuration_name']) ?> <small class="text-muted fw-normal"><?= e($format['product_name']) ?></small></h5>
                    <div class="hstack gap-3">
                        <span class="fs-12 text-muted" id="<?= e($p) ?>-summary"><?= e(number_format((int) $format['units_open'])) ?> open, <?= e(number_format($format['on_hand'])) ?> on hand, <span class="<?= $format['need'] > 0 ? 'text-danger fw-semibold' : '' ?>"><?= e(number_format($format['need'])) ?> to package</span></span>
                        <?php if ($canPackage && $format['need'] > 0): ?><?= nav_button($p . '-package-btn', '/orders/package?format=' . (int) $configId, 'Package', 'feather-box') ?><?php endif; ?>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="<?= e($p) ?>-table">
                        <thead class="thead-light"><tr><th>Order</th><th>Customer</th><th>Due</th><th>Open</th><th>In draft runs</th><th>Covered by stock</th><th>To package</th></tr></thead>
                        <tbody>
                        <?php foreach ($format['lines'] as $lineId => $line): $overdue = $line['requested_on'] < today(); ?>
                            <tr id="<?= e($p) ?>-line-<?= e($lineId) ?>">
                                <td><a <?= nav_attrs('/orders/' . (int) $line['sales_order_id']) ?>><?= e($line['order_number']) ?></a></td>
                                <td><?= e($line['customer_name']) ?></td>
                                <td class="<?= $overdue ? 'text-danger fw-semibold' : '' ?>"><?= e(format_date($line['requested_on'])) ?></td>
                                <td><?= e(number_format((int) $line['units_open'])) ?></td>
                                <td><?= e(number_format((int) $line['units_in_draft_runs'])) ?></td>
                                <td><?= e(number_format((int) $line['stock_covered'])) ?></td>
                                <td class="fw-semibold"><?= e(number_format((int) $line['need'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endforeach; ?>
    </div></div>
</div>
