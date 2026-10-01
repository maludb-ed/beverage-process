<?php /** @var array $vessels  @var array $plans  @var array $occupants  @var string $from  @var string $to  @var string $today */
$weeks = [];
$cursor = (new DateTimeImmutable($from))->modify('monday this week');
$end = new DateTimeImmutable($to);
while ($cursor <= $end && count($weeks) < 27) {
    $weeks[] = $cursor;
    $cursor = $cursor->modify('+1 week');
}
$occupantByVessel = [];
foreach ($occupants as $o) { $occupantByVessel[(int) $o['vessel_id']] = $o; }
$plansByVessel = [];
foreach ($plans as $plan) { $plansByVessel[(int) $plan['vessel_id']][] = $plan; }
$todayDate = new DateTimeImmutable($today);
?>
<div class="col-lg-12" id="production-calendar-grid">
    <div class="card stretch stretch-full" id="production-calendar-card">
        <div class="card-header"><h5 class="card-title">Planned vessel use</h5></div>
        <div class="card-body custom-card-action p-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0" id="production-calendar-table">
                    <thead class="thead-light">
                        <tr>
                            <th id="production-calendar-col-vessel">Vessel</th>
                            <?php foreach ($weeks as $w): ?><th id="production-calendar-col-<?= e($w->format('Y-m-d')) ?>">Week of <?= e($w->format('M j')) ?></th><?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody id="production-calendar-tbody">
                    <?php foreach ($vessels as $v): $vid = (int) $v['id']; ?>
                        <tr id="production-calendar-row-<?= e($vid) ?>">
                            <td id="production-calendar-row-<?= e($vid) ?>-vessel"><span class="fw-semibold"><?= e($v['name']) ?></span> <small class="text-muted"><?= fmt_qty_html($v['capacity_l'], 'L', 0) ?></small></td>
                            <?php foreach ($weeks as $w):
                                $ws = $w; $we = $w->modify('+6 days');
                                $cell = array_values(array_filter($plansByVessel[$vid] ?? [], static fn($pl) => $pl['planned_from'] <= $we->format('Y-m-d') && $pl['planned_to'] >= $ws->format('Y-m-d')));
                                $orderIds = array_unique(array_column($cell, 'order_id'));
                                $occ = $occupantByVessel[$vid] ?? null;
                                $showOcc = $occ !== null && $todayDate >= $ws && $todayDate <= $we;
                                $conflict = count($orderIds) >= 2
                                    || ($showOcc && $orderIds !== [] && array_diff($orderIds, [$occ['production_order_id'] ?? 0]) !== []);
                                $cid = 'production-calendar-cell-' . $vid . '-' . $ws->format('Ymd'); ?>
                                <td id="<?= e($cid) ?>" class="<?= $conflict ? 'bg-warning-subtle' : '' ?>"<?= $conflict ? ' data-bs-toggle="tooltip" title="conflict"' : '' ?>>
                                    <?php foreach ($cell as $pl): ?>
                                        <div><a id="<?= e($cid . '-order-' . $pl['id']) ?>" class="badge bg-soft-<?= e(production_order_status_color($pl['status'])) ?> text-<?= e(production_order_status_color($pl['status'])) ?>" <?= nav_attrs('/production-orders/' . (int) $pl['order_id']) ?>><?= e($pl['number'] . ' ' . humanize($pl['role'])) ?></a></div>
                                    <?php endforeach; ?>
                                    <?php if ($showOcc): ?><div><?= badge((string) $occ['occupant_label'], 'info', $cid . '-occupant') ?></div><?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($vessels === []): ?><tr id="production-calendar-empty"><td class="text-center text-muted py-5">No active vessels.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer fs-12 text-muted" id="production-calendar-footer">Shaded cells have two or more planned orders, or a planned order over another occupant.</div>
    </div>
</div>
