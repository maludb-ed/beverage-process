<?php /** @var array $rack  @var array $errors  @var array $areaOptions */
$id = $rack['id'] ?? null;
$isEdit = $id !== null;
$title = $isEdit ? 'Edit Rack' : 'Add Rack';
$p = 'rack-form';
$fromArea = ($rack['from'] ?? '') === 'area' && !empty($rack['parent_location_id']);
$backUrl = $fromArea ? '/locations/' . (int) $rack['parent_location_id'] . '/edit' : '/racks/';
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'rack-form', 'crumbs' => $fromArea ? ['Setup' => null, 'Locations' => '/locations/', 'Area' => $backUrl, $isEdit ? 'Edit rack' : 'Add rack' => null] : ['Inventory' => null, 'Rack board' => '/racks/', $isEdit ? 'Edit' : 'Add' => null], 'actionsHtml' => form_actions('rack-form', $backUrl, 'Save Rack')]) ?>
<div class="main-content" id="rack-form-content">
    <form id="rack-form" method="post" action="/racks/save" hx-post="/racks/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <?php if ($fromArea): ?><input type="hidden" name="from" value="area" /><?php endif; ?>
        <div class="row"><div class="col-lg-12">
            <div class="card" id="rack-form-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Storage rack</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">A rack stands in an area and takes its tax state. Put stock on it with a transfer or as packaging output.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => $errors, 'id' => 'rack-form-errors']) ?>
                    <?= form_select($p, 'parent_location_id', 'Area', $areaOptions, $rack['parent_location_id'] ?? '', $errors, ['required' => true, 'blank' => 'Choose an area', 'help' => 'Cannot change while stock is on the rack.']) ?>
                    <?= form_input($p, 'rack_number', 'Rack number', $rack['rack_number'] ?? '', $errors, ['required' => true, 'maxlength' => 12, 'icon' => 'feather-hash', 'autofocus' => !$isEdit, 'help' => 'Unique on the premises, such as 7 or A-12. The rack is named "Rack 7".']) ?>
                    <?= form_checkbox($p, 'active', 'Active', (bool) ($rack['active'] ?? true), ['last' => true]) ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
