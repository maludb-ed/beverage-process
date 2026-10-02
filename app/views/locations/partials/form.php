<?php /** @var array $location  @var array $errors  @var array $premisesOptions  @var array $racks */
$racks = $racks ?? [];
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
            <div class="card" id="location-form-card">
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
    <?php if ($isEdit && ($location['kind'] ?? '') !== 'outside'): ?>
    <div class="row"><div class="col-lg-12">
        <div class="card" id="location-form-racks-card">
            <div class="card-header">
                <h5 class="card-title">Racks in this area</h5>
                <?= nav_button('location-form-add-rack-btn', '/racks/new?area_id=' . (int) $id . '&from=area', 'Add Rack', 'feather-plus', 'btn btn-sm btn-primary') ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="location-form-racks-table">
                    <thead class="thead-light"><tr><th>Rack</th><th>Lots on it</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($racks as $rack): $rid = 'location-form-rack-' . (int) $rack['id']; ?>
                        <tr id="<?= e($rid) ?>">
                            <td id="<?= e($rid) ?>-name"><a <?= nav_attrs('/racks/?area_id=' . (int) $id . '&q=' . rawurlencode((string) $rack['rack_number'])) ?>><?= e($rack['name']) ?></a></td>
                            <td id="<?= e($rid) ?>-lots"><?= (int) $rack['lot_count'] > 0 ? e((int) $rack['lot_count']) : '<span class="text-muted">Empty</span>' ?></td>
                            <td id="<?= e($rid) ?>-status"><?= $rack['active'] ? badge('Active', 'success') : badge('Inactive', 'secondary') ?></td>
                            <td id="<?= e($rid) ?>-actions" class="text-end"><div class="hstack gap-2 justify-content-end"><?= row_edit_button($rid . '-edit-btn', '/racks/' . (int) $rack['id'] . '/edit?from=area') ?></div></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($racks === []): ?><tr id="location-form-racks-empty"><td colspan="4" class="text-center text-muted py-4">No racks yet. Add numbered racks to track exactly where stock sits in this area.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div></div>
    <?php endif; ?>
</div>
