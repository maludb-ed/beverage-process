<?php /** @var array $reservation  @var array $clashes  @var bool $canEdit  @var array|null $occupant */
$r = $reservation;
$id = (int) $r['id'];
$p = 'reservation-view';
$resourceUrl = $r['resource_kind'] === 'vessel' ? '/vessels/' . (int) $r['resource_id'] . '/edit' : '/equipment/' . (int) $r['resource_id'];
$actions = nav_button($p . '-schedule-btn', '/schedule/?resource=' . $r['resource_kind'] . ':' . (int) $r['resource_id'] . '&from=' . $r['local_from'], 'Schedule', 'feather-calendar', 'btn btn-light-brand');
if ($canEdit && $r['status'] === 'booked') {
    $actions .= nav_button($p . '-edit-btn', '/reservations/' . $id . '/edit', 'Edit', 'feather-edit', 'btn btn-light-brand')
        . '<button type="button" class="btn btn-primary" id="' . $p . '-cancel-btn" hx-post="/reservations/' . $id . '/cancel" hx-target="#page-content" hx-swap="innerHTML" hx-confirm="Cancel this reservation of ' . e($r['resource_name']) . '?"><i class="feather-x-circle me-2"></i><span>Cancel reservation</span></button>';
}
$for = $r['subject_number'] !== null
    ? '<a ' . nav_attrs(reservation_subject_url($r['subject_kind'], (int) $r['subject_id'])) . '>' . e($r['subject_number']) . '</a> <span class="text-muted">' . e($r['subject_label']) . '</span>'
    : e(humanize($r['kind']));
?>
<?= view('shared/page-header.php', ['title' => 'Reservation', 'screen' => $p, 'crumbs' => ['Production' => null, 'Equipment schedule' => '/schedule/', $r['resource_name'] => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="reservation-view-content">
    <div class="row">
        <div class="col-xxl-5 col-xl-6">
            <div class="card" id="reservation-view-summary">
                <div class="card-body">
                    <div class="mb-4 d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold mb-0"><?= e($r['resource_name']) ?></h5>
                        <div class="hstack gap-2"><?= $r['shared'] ? badge('Shared', 'warning', $p . '-shared') : '' ?><?= status_badge($r['status'], $p . '-status') ?></div>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-box"></i>Resource</span><span id="<?= e($p) ?>-resource"><a <?= nav_attrs($resourceUrl) ?>><?= e($r['resource_name']) ?></a> <small class="text-muted"><?= e(humanize($r['resource_type'])) ?></small></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-bookmark"></i>For</span><span id="<?= e($p) ?>-for"><?= $for ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-tag"></i>Role</span><span id="<?= e($p) ?>-role"><?= e(humanize($r['role'])) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-calendar"></i>When</span><span id="<?= e($p) ?>-when"><?= e(reservation_window_label($r)) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-clock"></i>Days</span><span id="<?= e($p) ?>-days"><?= e(reservation_days($r)) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-user"></i>Booked</span><span id="<?= e($p) ?>-booked"><?= e(($r['created_by_name'] ?? 'Migrated') . ', ' . format_date($r['created_at'])) ?></span></li>
                        <li class="hstack justify-content-between mb-0"><span class="text-muted fw-medium hstack gap-3"><i class="feather-x-circle"></i>Cancelled</span><span id="<?= e($p) ?>-cancelled"><?= $r['cancelled_at'] ? e(($r['cancelled_by_name'] ?? 'The run') . ', ' . format_date($r['cancelled_at'])) : '<span class="text-muted">No</span>' ?></span></li>
                    </ul>
                    <?php if ($r['notes']): ?><p class="mt-4 mb-0 text-muted fs-12" id="<?= e($p) ?>-notes"><?= e($r['notes']) ?></p><?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xxl-7 col-xl-6">
            <div class="card" id="reservation-view-clashes">
                <div class="card-header"><h5 class="card-title">Overlapping bookings</h5></div>
                <div class="card-body">
                    <?php if ($occupant !== null): ?><div class="mb-3" id="<?= e($p) ?>-occupant"><?= badge('Occupied now by ' . $occupant['label'], 'warning') ?></div><?php endif; ?>
                    <?php if ($clashes === []): ?>
                        <div class="text-muted" id="<?= e($p) ?>-clashes-none">None.</div>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0" id="<?= e($p) ?>-clash-list">
                        <?php foreach ($clashes as $c): $cid = $p . '-clash-' . (int) $c['reservation_id']; ?>
                            <li class="mb-2" id="<?= e($cid) ?>">
                                <?php if ($c['subject_number'] !== null): ?><a <?= nav_attrs(reservation_subject_url($c['subject_kind'], (int) $c['subject_id'])) ?>><?= e($c['subject_number']) ?></a> <span class="text-muted"><?= e($c['subject_label']) ?></span>
                                <?php else: ?><a <?= nav_attrs('/reservations/' . (int) $c['reservation_id']) ?>><?= e(humanize($c['kind'])) ?></a><?php endif; ?>
                                <span class="text-muted">· <?= e(humanize($c['role'])) ?> · <?= e(reservation_window_label($c)) ?></span>
                                <?= $c['shared'] ? badge('Shared', 'warning') : '' ?>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
