<?php /** @var array $reservation  @var array $errors  @var array $clashes  @var bool $allowShare  @var array $groups  @var array $subjectOptions  @var string|null $resourceName */
$id = $reservation['id'] ?? null;
$isEdit = $id !== null;
$title = $isEdit ? 'Edit Reservation' : 'Reserve Equipment';
$p = 'reservation-form';
$cancelUrl = $isEdit ? '/reservations/' . $id : '/schedule/';
$isRun = ($reservation['kind'] ?? 'run') === 'run';
$subjectKind = $reservation['subject_kind'] ?? 'production_order';
// The resource select, grouped Vessels / Equipment.
$resourceSelect = form_row_open($p, 'resource', 'Resource')
    . '<select class="form-select' . invalid_class($errors, 'resource') . '" id="' . e(field_id($p, 'resource')) . '" name="resource" required><option value="">Choose a vessel or equipment</option>';
foreach ($groups as $groupLabel => $options) {
    $resourceSelect .= '<optgroup label="' . e($groupLabel) . '">';
    foreach ($options as $key => $label) {
        $resourceSelect .= '<option value="' . e($key) . '"' . (($reservation['resource'] ?? '') === $key ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    $resourceSelect .= '</optgroup>';
}
$resourceSelect .= '</select>' . (isset($errors['resource']) ? '<div class="invalid-feedback d-block">' . e($errors['resource']) . '</div>' : '') . form_row_close('Tanks and presses are vessels; mills, pumps, filters and lines are equipment.');
$roleOptions = ['' => 'Choose a role'] + RESERVATION_ROLES;
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'reservation-form', 'crumbs' => ['Production' => null, 'Equipment schedule' => '/schedule/', $isEdit ? 'Edit' : 'Reserve' => null], 'actionsHtml' => form_actions('reservation-form', $cancelUrl, $isEdit ? 'Save Reservation' : 'Reserve')]) ?>
<div class="main-content" id="reservation-form-content">
    <form id="reservation-form" method="post" action="/reservations/save" hx-post="/reservations/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <div class="row"><div class="col-lg-12">
            <?php if ($clashes !== []): ?>
            <div class="card border-warning" id="reservation-form-clashes">
                <div class="card-body">
                    <h6 class="fw-bold mb-2"><i class="feather-alert-triangle text-warning me-2"></i><?= e($errors['clashes'] ?? 'This window overlaps other bookings.') ?></h6>
                    <ul class="mb-0 fs-12" id="reservation-form-clash-list">
                        <?php foreach (reservation_clash_labels($resourceName ?? 'This resource', $clashes) as $i => $line): ?><li id="reservation-form-clash-<?= e($i) ?>"><?= e($line) ?></li><?php endforeach; ?>
                    </ul>
                    <?php if ($allowShare): ?>
                    <div class="form-check mt-3" id="reservation-form-share-row">
                        <input type="hidden" name="share" value="0" />
                        <input class="form-check-input" type="checkbox" id="reservation-form-field-share" name="share" value="1"<?= !empty($reservation['share']) ? ' checked' : '' ?> />
                        <label class="form-check-label fw-semibold" for="reservation-form-field-share">Book anyway (shared use)</label>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            <div class="card" id="reservation-form-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Reservation</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Book a tank, a press, a line or a piece of equipment for a run, or block it for cleaning, maintenance or a hold. A booking is for whole days unless you give times.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_diff_key($errors, ['clashes' => 1]), 'id' => 'reservation-form-errors']) ?>
                    <?= $resourceSelect ?>
                    <?= form_select($p, 'kind', 'For', RESERVATION_KINDS, $reservation['kind'] ?? 'run', $errors, ['required' => true,
                        'extra' => ' hx-on:change="document.getElementById(\'reservation-form-run-rows\').classList.toggle(\'d-none\', this.value !== \'run\')"']) ?>
                    <div id="reservation-form-run-rows" class="<?= $isRun ? '' : 'd-none' ?>">
                        <?= form_select($p, 'subject_kind', 'Kind of run', RESERVATION_SUBJECT_KINDS, $subjectKind, $errors, [
                            'extra' => ' hx-get="/reservations/subject-options" hx-trigger="change" hx-target="#reservation-form-subject-slot" hx-swap="innerHTML"']) ?>
                        <?= form_row_open($p, 'subject', 'Run') ?><div id="reservation-form-subject-slot"><?= view('reservations/partials/subject-options.php', ['subjectKind' => $subjectKind, 'subjectOptions' => $subjectOptions, 'selected' => $reservation['subject_id'] ?? null, 'errors' => $errors]) ?></div><?= form_row_close('Open runs only: planned, released or in-progress orders, active batches, draft press and packaging runs.') ?>
                    </div>
                    <?= form_select($p, 'role', 'Role', $roleOptions, $reservation['role'] ?? '', $errors, ['help' => 'What the resource does in this run: primary, maturation, brite, blend, press, mill, transfer, filter, carbonate, package.']) ?>
                    <?= form_input($p, 'planned_from', 'From', $reservation['planned_from'] ?? '', $errors, ['type' => 'date', 'required' => true, 'icon' => 'feather-calendar']) ?>
                    <?= form_input($p, 'planned_to', 'To', $reservation['planned_to'] ?? '', $errors, ['type' => 'date', 'required' => true, 'icon' => 'feather-calendar', 'help' => 'Inclusive: a fermenter booked Nov 2 to Nov 16 is held through Nov 16.']) ?>
                    <?= form_checkbox($p, 'all_day', 'All day', (bool) ($reservation['all_day'] ?? true), ['help' => 'Off: give a start and an end time, so two runs can share a line in one day.']) ?>
                    <?= form_input($p, 'start_time', 'Start time', $reservation['start_time'] ?? '', $errors, ['type' => 'time', 'icon' => 'feather-clock']) ?>
                    <?= form_input($p, 'end_time', 'End time', $reservation['end_time'] ?? '', $errors, ['type' => 'time', 'icon' => 'feather-clock']) ?>
                    <?= form_textarea($p, 'notes', 'Notes', $reservation['notes'] ?? '', $errors, ['last' => true]) ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
