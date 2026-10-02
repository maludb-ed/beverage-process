<?php /** @var array $rows  @var array $query  @var bool $canPrice */
$totalUnits = array_sum(array_map(static fn($r) => (int) $r['units'], $rows));
$totalValue = array_sum(array_map(static fn($r) => (float) $r['value'], $rows));
$sortLink = static function (string $key, string $label) use ($query): string {
    $current = $query['sort'];
    $next = $current === '-' . $key ? $key : '-' . $key;
    $url = '/reports/orders?' . http_build_query(array_merge($query, ['sort' => $next]));
    return '<a href="' . e($url) . '" hx-get="' . e($url) . '" hx-target="#report-orders-results" hx-swap="outerHTML" id="report-orders-sort-' . e($key) . '">' . e($label) . ($current === $key ? ' ↑' : ($current === '-' . $key ? ' ↓' : '')) . '</a>';
};
?>
<div class="col-lg-12" id="report-orders-results">
    <div class="card">
        <div class="card-header"><h5 class="card-title">Orders due <?= e(format_date($query['date_from'])) ?> to <?= e(format_date($query['date_to'])) ?></h5><span class="fs-12 text-muted">Confirmed, in fulfillment, shipped and closed orders, history included</span></div>
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="report-orders-table">
                <thead class="thead-light"><tr>
                    <th><?= $sortLink('group', REPORT_ORDER_GROUP_BY[$query['group_by']]) ?></th><th><?= $sortLink('orders', 'Orders') ?></th><th><?= $sortLink('units', 'Units ordered') ?></th><th>Units shipped</th><th>Share of units</th>
                    <?php if ($canPrice): ?><th class="text-end"><?= $sortLink('value', 'Value') ?></th><?php endif; ?>
                </tr></thead>
                <tbody>
                <?php foreach ($rows as $i => $r): ?>
                    <tr id="report-orders-row-<?= e($i) ?>">
                        <td><?= e($r['group_label']) ?></td>
                        <td><?= e(number_format((int) $r['orders'])) ?></td>
                        <td><?= e(number_format((int) $r['units'])) ?></td>
                        <td><?= e(number_format((int) $r['units_shipped'])) ?></td>
                        <td><?= $totalUnits > 0 ? e(number_format(100 * (int) $r['units'] / $totalUnits, 1)) . '%' : '—' ?></td>
                        <?php if ($canPrice): ?><td class="text-end"><?= $r['value'] !== null ? '$' . e(number_format((float) $r['value'], 2)) : '—' ?><?= (int) $r['priced_units'] < (int) $r['units'] && $r['value'] !== null ? '<div class="fs-11 text-muted">some lines unpriced</div>' : '' ?></td><?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                <?php if ($rows === []): ?><tr id="report-orders-empty"><td colspan="<?= $canPrice ? 6 : 5 ?>" class="text-center text-muted py-4">No orders due in this period.</td></tr><?php else: ?>
                    <tr class="fw-semibold" id="report-orders-total"><td>Total</td><td><?= e(number_format(array_sum(array_map(static fn($r) => (int) $r['orders'], $rows)))) ?></td><td><?= e(number_format($totalUnits)) ?></td><td><?= e(number_format(array_sum(array_map(static fn($r) => (int) $r['units_shipped'], $rows)))) ?></td><td>100%</td><?php if ($canPrice): ?><td class="text-end">$<?= e(number_format($totalValue, 2)) ?></td><?php endif; ?></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
