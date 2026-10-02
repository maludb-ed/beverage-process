<?php /** @var array $p  @var array $user  @var bool $canPrice */
$s = 'planning-purchasing';
$canCreate = user_can($user, 'receiving');
$actions = view('planning/partials/levels.php', ['screen' => $s, 'url' => '/planning/purchasing', 'level' => $p['level']]);
?>
<?= view('shared/page-header.php', ['title' => 'Suggested purchases', 'screen' => $s, 'crumbs' => ['Planning' => '/planning/', 'Suggested purchases' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="<?= $s ?>-content">
    <div class="row"><div class="col-lg-12">
        <?= view('planning/partials/warnings.php', ['warnings' => $p['warnings'], 'screen' => $s]) ?>
        <div class="card" id="<?= $s ?>-suggestions-card">
            <div class="card-header"><h5 class="card-title">To buy</h5><span class="fs-12 text-muted">Released stock and open purchase orders against packaging, suggested batches, production orders and draft packaging runs.</span></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="<?= $s ?>-table">
                    <thead class="thead-light"><tr><th>Item</th><th>Short first</th><th>Short in <?= e(count($p['weeks'])) ?> weeks</th><th>Order by</th><th>Supplier</th><th>Suggested order</th><th>Driven by</th><th class="text-end">Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($p['purchasing'] as $itemId => $x): $item = $x['item']; $sup = $x['supplier']; ?>
                        <tr id="<?= $s ?>-row-<?= e($itemId) ?>">
                            <td><?= e($item['code']) ?> <small class="text-muted"><?= e($item['name']) ?></small><div class="fs-11 text-muted"><?= e(implode(', ', $x['sources'])) ?></div></td>
                            <td><?= fmt_qty_html($x['short_first'], $item['base_unit_code']) ?><div class="fs-11 text-muted">by <?= e(format_date($x['needed_by'])) ?></div></td>
                            <td><?= fmt_qty_html($x['short_total'], $item['base_unit_code']) ?><?= $x['fruit_kg'] !== null ? '<div class="fs-11 text-muted">or about ' . e(fmt_qty($x['fruit_kg'], 'kg', 0, 'fruit')) . ' of fruit to press</div>' : '' ?></td>
                            <td class="<?= $x['late'] ? 'text-danger fw-semibold' : '' ?>"><?= $x['order_by'] ? e(format_date($x['order_by'])) . ($x['late'] ? ' <small>(late)</small>' : '') : '<span class="text-muted">No lead time</span>' ?></td>
                            <td><?= $sup ? e($sup['supplier_name']) . ($sup['lead_time_days'] !== null ? '<div class="fs-11 text-muted">' . e($sup['lead_time_days']) . ' days</div>' : '') : '<span class="text-muted">None</span>' ?></td>
                            <td><?= $x['purchase_qty'] !== null ? e(number_format((float) $x['purchase_qty'])) . ' ' . e($sup['purchase_unit_code']) . ($canPrice && $sup['last_price'] !== null ? '<div class="fs-11 text-muted">about $' . e(number_format($x['purchase_qty'] * (float) $sup['last_price'], 2)) . '</div>' : '') : '—' ?></td>
                            <td><?= view('planning/partials/driver.php', ['driver' => $x['driver']]) ?></td>
                            <td class="text-end">
                                <?php if ($canCreate): ?><?= nav_button($s . '-row-' . $itemId . '-create-btn', '/purchase-orders/new?item=' . rawurlencode($item['code']) . ($sup ? '&supplier=' . rawurlencode($sup['supplier_name']) . '&qty=' . rawurlencode((string) $x['purchase_qty']) : '') . '&expected_on=' . rawurlencode($x['needed_by']), 'Create purchase order', 'feather-plus', 'btn btn-sm btn-light-brand') ?><?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($p['purchasing'] === []): ?><tr id="<?= $s ?>-empty"><td colspan="8" class="text-center text-muted py-4">Nothing to buy: stock and open purchase orders cover the next <?= e(count($p['weeks'])) ?> weeks.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card" id="<?= $s ?>-materials-card">
            <div class="card-header"><h5 class="card-title">Every material needed</h5></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="<?= $s ?>-materials-table">
                    <thead class="thead-light"><tr><th>Item</th><th>Needed in <?= e(count($p['weeks'])) ?> weeks</th><th>Released on hand</th><th>On order</th><th>Short</th></tr></thead>
                    <tbody>
                    <?php foreach ($p['materials'] as $itemId => $m): $short = array_sum($m['short']); ?>
                        <tr id="<?= $s ?>-material-<?= e($itemId) ?>">
                            <td><?= e($m['item']['code']) ?> <small class="text-muted"><?= e($m['item']['name']) ?></small></td>
                            <td><?= fmt_qty_html(array_sum($m['need']), $m['item']['base_unit_code']) ?></td>
                            <td><?= fmt_qty_html($m['on_hand'], $m['item']['base_unit_code']) ?></td>
                            <td><?= fmt_qty_html($m['on_order'], $m['item']['base_unit_code']) ?></td>
                            <td class="<?= $short > 0 ? 'text-danger fw-semibold' : '' ?>"><?= fmt_qty_html($short, $m['item']['base_unit_code']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($p['materials'] === []): ?><tr><td colspan="5" class="text-center text-muted py-4">No materials needed in the horizon.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div></div>
</div>
