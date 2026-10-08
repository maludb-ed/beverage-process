<?php /** @var array $equipment  @var array $bookings  @var bool $canEdit */
$id = (int) $equipment['id'];
$p = 'equipment-view';
$actions = nav_button($p . '-schedule-btn', '/schedule/?resource=equipment:' . $id, 'Schedule', 'feather-calendar', 'btn btn-light-brand');
if ($canEdit) {
    $actions .= nav_button($p . '-reserve-btn', '/reservations/new?resource=equipment:' . $id, 'Reserve', 'feather-bookmark', 'btn btn-light-brand')
        . nav_button($p . '-edit-btn', '/equipment/' . $id . '/edit', 'Edit', 'feather-edit', 'btn btn-primary');
}
$statusButton = static fn(string $status, string $label, string $icon) => $equipment['status'] === $status ? '' :
    '<button type="button" class="btn btn-sm btn-light-brand" id="' . e($p . '-set-' . str_replace('_', '-', $status) . '-btn') . '" hx-post="/equipment/' . e($id) . '/status" hx-vals=\'{"status": "' . e($status) . '"}\' hx-target="#page-content" hx-swap="innerHTML"'
    . ($status === 'out_of_service' ? ' hx-confirm="Take ' . e($equipment['name']) . ' out of service? Bookings ahead stay and are shaded on the schedule."' : '') . '><i class="' . e($icon) . ' me-1"></i>' . e($label) . '</button>';
$bookingRows = static function (array $rows, string $prefix): string {
    $html = '';
    foreach ($rows as $b) {
        $bid = (int) $b['id'];
        $subject = $b['subject_number'] !== null
            ? '<a ' . nav_attrs(reservation_subject_url($b['subject_kind'], (int) $b['subject_id'])) . '>' . e($b['subject_number']) . '</a> <small class="text-muted">' . e($b['subject_label']) . '</small>'
            : '<a ' . nav_attrs('/reservations/' . $bid) . '>' . e(humanize($b['kind'])) . '</a>' . ($b['notes'] ? ' <small class="text-muted">' . e($b['notes']) . '</small>' : '');
        $html .= '<tr id="' . e($prefix . '-row-' . $bid) . '">'
            . '<td id="' . e($prefix . '-row-' . $bid . '-when') . '">' . e(reservation_window_label($b)) . '</td>'
            . '<td id="' . e($prefix . '-row-' . $bid . '-for') . '">' . $subject . '</td>'
            . '<td id="' . e($prefix . '-row-' . $bid . '-role') . '">' . e(humanize($b['role'])) . '</td>'
            . '<td id="' . e($prefix . '-row-' . $bid . '-flags') . '">' . ($b['shared'] ? badge('Shared', 'warning') : '') . ((int) $b['clash_count'] > 0 ? ' ' . badge((int) $b['clash_count'] . ' overlap' . ((int) $b['clash_count'] === 1 ? '' : 's'), 'warning') : '') . '</td>'
            . '</tr>';
    }
    return $html;
};
?>
<?= view('shared/page-header.php', ['title' => $equipment['name'], 'screen' => $p, 'crumbs' => ['Setup' => null, 'Equipment' => '/equipment/', $equipment['name'] => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="equipment-view-content">
    <div class="row">
        <div class="col-xxl-4 col-xl-5">
            <div class="card" id="equipment-view-summary">
                <div class="card-body">
                    <div class="mb-4 d-flex align-items-center justify-content-between">
                        <h5 class="fw-bold mb-0"><?= e($equipment['name']) ?></h5>
                        <?= status_badge($equipment['status'], 'equipment-view-status') ?>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-tool"></i>Kind</span><span id="equipment-view-kind"><?= e(EQUIPMENT_KINDS[$equipment['kind']] ?? humanize($equipment['kind'])) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-activity"></i>Rating</span><span id="equipment-view-rating"><?= $equipment['rating'] ? e($equipment['rating']) : '<span class="text-muted">Not given</span>' ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-home"></i>Premises</span><span id="equipment-view-premises"><?= e($equipment['premises_name']) ?></span></li>
                        <li class="hstack justify-content-between mb-4"><span class="text-muted fw-medium hstack gap-3"><i class="feather-map-pin"></i>Location</span><span id="equipment-view-location"><?= $equipment['location_name'] ? e($equipment['location_name']) : '<span class="text-muted">Not placed</span>' ?></span></li>
                        <li class="hstack justify-content-between mb-0"><span class="text-muted fw-medium hstack gap-3"><i class="feather-toggle-left"></i>Active</span><span id="equipment-view-active"><?= e(yes_no($equipment['active'])) ?></span></li>
                    </ul>
                    <?php if ($equipment['notes']): ?><p class="mt-4 mb-0 text-muted fs-12" id="equipment-view-notes"><?= e($equipment['notes']) ?></p><?php endif; ?>
                    <?php if ($canEdit): ?>
                    <div class="d-flex flex-wrap gap-2 mt-4" id="equipment-view-status-actions">
                        <?= $statusButton('available', 'Available', 'feather-check') . $statusButton('cleaning', 'Cleaning', 'feather-droplet') . $statusButton('out_of_service', 'Out of service', 'feather-x-octagon') ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xxl-8 col-xl-7">
            <div class="card" id="equipment-view-upcoming">
                <div class="card-header"><h5 class="card-title">Booked ahead</h5></div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="equipment-view-upcoming-table">
                        <thead class="thead-light"><tr><th>When</th><th>For</th><th>Role</th><th></th></tr></thead>
                        <tbody>
                        <?= $bookingRows($bookings['upcoming'], 'equipment-view-upcoming') ?>
                        <?php if ($bookings['upcoming'] === []): ?><tr id="equipment-view-upcoming-empty"><td colspan="4" class="text-center text-muted py-4">Nothing booked ahead.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card" id="equipment-view-past">
                <div class="card-header"><h5 class="card-title">Past bookings</h5></div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" id="equipment-view-past-table">
                        <thead class="thead-light"><tr><th>When</th><th>For</th><th>Role</th><th></th></tr></thead>
                        <tbody>
                        <?= $bookingRows($bookings['past'], 'equipment-view-past') ?>
                        <?php if ($bookings['past'] === []): ?><tr id="equipment-view-past-empty"><td colspan="4" class="text-center text-muted py-4">No past bookings.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
