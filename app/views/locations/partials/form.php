<?php /** @var array $location  @var array $errors  @var array $premisesOptions */
$id = $location['id'] ?? null;
$isEdit = $id !== null;
$title = $isEdit ? 'Edit Location' : 'Add Location';
$p = 'location-form';
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'location-form', 'crumbs' => ['Setup' => null, 'Locations' => '/locations/', $isEdit ? 'Edit' : 'Add' => null], 'actionsHtml' => form_actions('location-form', '/locations/', 'Save Location')]) ?>
<div class="main-content" id="location-form-content">
    <form id="location-form" method="post" action="/locations/save" hx-post="/locations/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <div class="row"><div class="col-lg-12">
            <div class="card stretch stretch-full" id="location-form-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Stock location</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Lots are held at locations. The tax state decides whether stock is in bond or tax paid.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => $errors, 'id' => 'location-form-errors']) ?>
                    <?= form_select($p, 'premises_id', 'Premises', $premisesOptions, $location['premises_id'] ?? '', $errors, ['required' => true, 'blank' => 'Choose a premises']) ?>
                    <?= form_input($p, 'name', 'Name', $location['name'] ?? '', $errors, ['required' => true, 'maxlength' => 120, 'icon' => 'feather-map-pin', 'autofocus' => !$isEdit]) ?>
                    <?= form_select($p, 'kind', 'Kind', LOCATION_KINDS, $location['kind'] ?? 'cellar', $errors, ['required' => true]) ?>
                    <?= form_select($p, 'tax_state', 'Tax state', LOCATION_TAX_STATES, $location['tax_state'] ?? 'bonded', $errors, ['required' => true, 'help' => 'Cannot change while stock remains at this location.']) ?>
                    <?= form_checkbox($p, 'allow_negative', 'Allow negative stock', (bool) ($location['allow_negative'] ?? false)) ?>
                    <?= form_checkbox($p, 'active', 'Active', (bool) ($location['active'] ?? true), ['last' => true]) ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
