<?php /** @var array $equipment  @var array $errors  @var array $premisesOptions  @var array $locationChoices */
$id = $equipment['id'] ?? null;
$isEdit = $id !== null;
$title = $isEdit ? 'Edit Equipment' : 'Add Equipment';
$p = 'equipment-form';
$cancelUrl = $isEdit ? '/equipment/' . $id : '/equipment/';
// Locations grouped under their premises; the server checks the pairing. Optional: a pump may stand anywhere.
$locationSelect = form_row_open($p, 'location_id', 'Location')
    . '<select class="form-select' . invalid_class($errors, 'location_id') . '" id="' . e(field_id($p, 'location_id')) . '" name="location_id"><option value="">Not placed</option>';
$groups = [];
foreach ($locationChoices as $choice) {
    $groups[$choice['premises_name']][] = $choice;
}
foreach ($groups as $premisesName => $choices) {
    $locationSelect .= '<optgroup label="' . e($premisesName) . '">';
    foreach ($choices as $choice) {
        $locationSelect .= '<option value="' . e($choice['id']) . '"' . ((string) ($equipment['location_id'] ?? '') === (string) $choice['id'] ? ' selected' : '') . '>' . e($choice['premises_name'] . ' — ' . $choice['name']) . '</option>';
    }
    $locationSelect .= '</optgroup>';
}
$locationSelect .= '</select>' . (isset($errors['location_id']) ? '<div class="invalid-feedback d-block">' . e($errors['location_id']) . '</div>' : '')
    . form_row_close('The area it stands in, when it has one.');
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'equipment-form', 'crumbs' => ['Setup' => null, 'Equipment' => '/equipment/', $isEdit ? 'Edit' : 'Add' => null], 'actionsHtml' => form_actions('equipment-form', $cancelUrl, 'Save Equipment')]) ?>
<div class="main-content" id="equipment-form-content">
    <form id="equipment-form" method="post" action="/equipment/save" hx-post="/equipment/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <div class="row"><div class="col-lg-12">
            <div class="card" id="equipment-form-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Equipment</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Mills, pumps, filters, chillers, carbonators and lines — what a run needs that holds no liquid. Tanks and presses are vessels.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => $errors, 'id' => 'equipment-form-errors']) ?>
                    <?php if (count($premisesOptions) === 1): ?>
                        <input type="hidden" id="<?= e(field_id($p, 'premises_id')) ?>" name="premises_id" value="<?= e(array_key_first($premisesOptions)) ?>" />
                    <?php else: ?>
                        <?= form_select($p, 'premises_id', 'Premises', $premisesOptions, $equipment['premises_id'] ?? '', $errors, ['required' => true, 'blank' => 'Choose a premises']) ?>
                    <?php endif; ?>
                    <?= form_input($p, 'name', 'Name', $equipment['name'] ?? '', $errors, ['required' => true, 'maxlength' => 120, 'icon' => 'feather-tool', 'autofocus' => !$isEdit]) ?>
                    <?= form_select($p, 'kind', 'Kind', EQUIPMENT_KINDS, $equipment['kind'] ?? 'other', $errors, ['required' => true]) ?>
                    <?= form_input($p, 'rating', 'Rating', $equipment['rating'] ?? '', $errors, ['maxlength' => 120, 'icon' => 'feather-activity', 'placeholder' => '120 cans/min, 4,000 L/h', 'help' => 'In words: what it does in an hour or a minute.']) ?>
                    <?= $locationSelect ?>
                    <?= form_select($p, 'status', 'Status', EQUIPMENT_STATUSES, $equipment['status'] ?? 'available', $errors, ['required' => true]) ?>
                    <?= form_textarea($p, 'notes', 'Notes', $equipment['notes'] ?? '', $errors, ['last' => !$isEdit]) ?>
                    <?php if ($isEdit): ?><?= form_checkbox($p, 'active', 'Active', (bool) ($equipment['active'] ?? true), ['last' => true, 'help' => 'Inactive equipment cannot be booked and is listed last.']) ?><?php endif; ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
