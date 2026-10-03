<?php /** @var array $byDate  @var array $overdue  @var array $undated  @var string $month  @var string $today */
$first = new DateTimeImmutable($month . '-01');
$last = $first->modify('last day of this month');
$prev = $first->modify('-1 month')->format('Y-m');
$next = $first->modify('+1 month')->format('Y-m');
$gridStart = $first->modify('monday this week');
$gridEnd = $last->modify('sunday this week');
$weeks = [];
for ($cursor = $gridStart; $cursor <= $gridEnd; $cursor = $cursor->modify('+1 week')) {
    $weeks[] = $cursor;
}
$lineColor = static fn(array $line): string => $line['overdue'] ? 'danger' : ($line['po_status'] === 'partial' ? 'warning' : 'primary');
$monthNav = static fn(string $id, string $target, string $icon, string $label): string => '<button type="button" class="btn btn-sm btn-light-brand" id="' . e($id) . '" hx-get="/receipts/projected?month=' . e($target) . '" hx-target="#receipts-projected-grid" hx-swap="outerHTML" hx-push-url="/receipts/projected?month=' . e($target) . '" aria-label="' . e($label) . '"><i class="' . e($icon) . '"></i></button>';
$offList = array_merge($overdue, $undated);
?>
<div class="col-lg-12" id="receipts-projected-grid">
    <div class="card" id="receipts-projected-card">
        <div class="card-header">
            <h5 class="card-title" id="receipts-projected-month-title">Expected shipments, <?= e($first->format('F Y')) ?></h5>
            <div class="hstack gap-2">
                <?= $monthNav('receipts-projected-prev-btn', $prev, 'feather-chevron-left', 'Previous month') ?>
                <?= $monthNav('receipts-projected-today-btn', substr($today, 0, 7), 'feather-calendar', 'This month') ?>
                <?= $monthNav('receipts-projected-next-btn', $next, 'feather-chevron-right', 'Next month') ?>
            </div>
        </div>
        <div class="card-body custom-card-action p-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0 receipts-projected-table" id="receipts-projected-table">
                    <thead class="thead-light">
                        <tr>
                            <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dow): ?><th id="receipts-projected-col-<?= e(strtolower($dow)) ?>"><?= e($dow) ?></th><?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody id="receipts-projected-tbody">
                    <?php foreach ($weeks as $week): ?>
                        <tr id="receipts-projected-week-<?= e($week->format('Ymd')) ?>">
                        <?php for ($d = 0; $d < 7; $d++):
                            $day = $week->modify('+' . $d . ' days');
                            $ymd = $day->format('Y-m-d');
                            $inMonth = $day >= $first && $day <= $last;
                            $cid = 'receipts-projected-day-' . $day->format('Ymd');
                            $byOrder = [];
                            foreach ($byDate[$ymd] ?? [] as $line) { $byOrder[(int) $line['purchase_order_id']][] = $line; } ?>
                            <td id="<?= e($cid) ?>" class="<?= $inMonth ? '' : 'bg-light text-muted' ?><?= $ymd === $today ? ' receipts-projected-today' : '' ?>">
                                <div class="fs-12 fw-semibold mb-1<?= $inMonth ? '' : ' text-muted' ?>" id="<?= e($cid) ?>-number"><?= $ymd === $today ? badge($day->format('j'), 'primary') : e($day->format('j')) ?></div>
                                <?php foreach ($byOrder as $orderId => $lines): $head = $lines[0]; $color = $lineColor($head); ?>
                                    <div class="mb-2" id="<?= e($cid) ?>-order-<?= e($orderId) ?>">
                                        <a class="badge bg-soft-<?= e($color) ?> text-<?= e($color) ?>" <?= nav_attrs('/purchase-orders/' . $orderId) ?>><?= e($head['number']) ?></a>
                                        <div class="fs-12 text-truncate" id="<?= e($cid) ?>-order-<?= e($orderId) ?>-supplier"><a <?= nav_attrs('/suppliers/' . (int) $head['supplier_id']) ?>><?= e($head['supplier_name']) ?></a></div>
                                        <?php foreach ($lines as $line): ?>
                                            <div class="fs-11 text-muted" id="receipts-projected-line-<?= e((int) $line['line_id']) ?>"><?= e($line['item_name']) ?>, <?= fmt_qty_html($line['qty_outstanding_base'], $line['base_unit_code'], 1) ?></div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                        <?php endfor; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer fs-12 text-muted" id="receipts-projected-footer">Open and partially received purchase orders only. Each line shows on its own expected date, or the order's when the line has none. <?= badge('Open', 'primary') ?> <?= badge('Partially received', 'warning') ?> <?= badge('Overdue', 'danger') ?></div>
    </div>
    <div class="card" id="receipts-projected-off-card">
        <div class="card-header"><h5 class="card-title">Overdue and undated</h5></div>
        <div class="card-body custom-card-action p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="receipts-projected-off-table">
                    <thead class="thead-light"><tr><th id="receipts-projected-off-col-order">Order</th><th id="receipts-projected-off-col-supplier">Vendor</th><th id="receipts-projected-off-col-item">Item</th><th id="receipts-projected-off-col-outstanding">Outstanding</th><th id="receipts-projected-off-col-expected">Expected</th></tr></thead>
                    <tbody id="receipts-projected-off-tbody">
                    <?php foreach ($offList as $line): $lid = (int) $line['line_id']; ?>
                        <tr id="receipts-projected-off-row-<?= e($lid) ?>">
                            <td id="receipts-projected-off-row-<?= e($lid) ?>-order"><a <?= nav_attrs('/purchase-orders/' . (int) $line['purchase_order_id']) ?>><?= status_dot($lineColor($line)) ?><?= e($line['number']) ?></a></td>
                            <td id="receipts-projected-off-row-<?= e($lid) ?>-supplier"><a <?= nav_attrs('/suppliers/' . (int) $line['supplier_id']) ?>><?= e($line['supplier_name']) ?></a></td>
                            <td id="receipts-projected-off-row-<?= e($lid) ?>-item"><?= e($line['item_name']) ?></td>
                            <td id="receipts-projected-off-row-<?= e($lid) ?>-outstanding"><?= fmt_qty_html($line['qty_outstanding_base'], $line['base_unit_code'], 1) ?></td>
                            <td id="receipts-projected-off-row-<?= e($lid) ?>-expected"><?= $line['expected_on'] === null ? badge('No date', 'secondary') : badge(format_date($line['expected_on']), 'danger') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($offList === []): ?><tr id="receipts-projected-off-empty"><td colspan="5" class="text-center text-muted py-4">Nothing overdue, and every open line has an expected date.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
