<?php /** @var array $vessel  @var array $errors  @var array $premisesOptions  @var array $locationChoices */
$id = $vessel['id'] ?? null;
$isEdit = $id !== null;
$title = $isEdit ? 'Edit Vessel' : 'Add Vessel';
$p = 'vessel-form';
$inUse = ($vessel['status'] ?? '') === 'in_use';
$statusOptions = $inUse ? ['in_use' => 'In use'] + VESSEL_SETTABLE_STATUSES : VESSEL_SETTABLE_STATUSES;
// Locations grouped under their premises; the server checks the pairing.
$locationSelect = form_row_open($p, 'location_id', 'Location')
    . '<select class="form-select' . invalid_class($errors, 'location_id') . '" id="' . e(field_id($p, 'location_id')) . '" name="location_id" required><option value="">Choose a location</option>';
$groups = [];
foreach ($locationChoices as $choice) {
    $groups[$choice['premises_name']][] = $choice;
}
foreach ($groups as $premisesName => $choices) {
    $locationSelect .= '<optgroup label="' . e($premisesName) . '">';
    foreach ($choices as $choice) {
        $locationSelect .= '<option value="' . e($choice['id']) . '"' . ((string) ($vessel['location_id'] ?? '') === (string) $choice['id'] ? ' selected' : '') . '>' . e($choice['premises_name'] . ' — ' . $choice['name']) . '</option>';
    }
    $locationSelect .= '</optgroup>';
}
$locationSelect .= '</select>' . (isset($errors['location_id']) ? '<div class="invalid-feedback d-block">' . e($errors['location_id']) . '</div>' : '')
    . form_row_close('Cellar, cold room, receiving or outside locations on the chosen premises.');
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'vessel-form', 'crumbs' => ['Setup' => null, 'Vessels' => '/vessels/', $isEdit ? 'Edit' : 'Add' => null], 'actionsHtml' => form_actions('vessel-form', '/vessels/', 'Save Vessel')]) ?>
<div class="main-content" id="vessel-form-content">
    <form id="vessel-form" method="post" action="/vessels/save" hx-post="/vessels/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <div class="row"><div class="col-lg-12">
            <div class="card stretch stretch-full" id="vessel-form-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Vessel</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Tanks, fermenters, totes and barrels that hold liquid. Capacity is entered in <?= e(display_unit('L')) ?>.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => $errors, 'id' => 'vessel-form-errors']) ?>
                    <?= form_select($p, 'premises_id', 'Premises', $premisesOptions, $vessel['premises_id'] ?? '', $errors, ['required' => true, 'blank' => 'Choose a premises']) ?>
                    <?= $locationSelect ?>
                    <?= form_input($p, 'name', 'Name', $vessel['name'] ?? '', $errors, ['required' => true, 'maxlength' => 120, 'icon' => 'feather-box', 'autofocus' => !$isEdit]) ?>
                    <?= form_select($p, 'kind', 'Kind', VESSEL_KINDS, $vessel['kind'] ?? 'tank', $errors, ['required' => true]) ?>
                    <?= form_input($p, 'capacity', 'Capacity', $vessel['capacity'] ?? '', $errors, ['type' => 'number', 'min' => '0', 'step' => 'any', 'required' => true, 'icon' => 'feather-droplet', 'suffix' => display_unit('L')]) ?>
                    <?= form_select($p, 'status', 'Status', $statusOptions, $vessel['status'] ?? 'empty', $errors, ['required' => !$inUse, 'disabled' => $inUse, 'help' => $inUse ? 'In use; set by the work that is using this vessel.' : null]) ?>
                    <?= form_textarea($p, 'notes', 'Notes', $vessel['notes'] ?? '', $errors) ?>
                    <?= form_checkbox($p, 'active', 'Active', (bool) ($vessel['active'] ?? true), ['last' => true]) ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
