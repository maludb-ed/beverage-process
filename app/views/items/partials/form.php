<?php /** @var array $item  @var array $classes  @var array $errors */
$id = $item['id'] ?? null;
$isEdit = $id !== null;
$fromMaterials = ($item['return_to'] ?? '') === 'materials';
$title = $isEdit ? 'Edit Item' : ($fromMaterials ? 'Add Material' : 'Add Item');
$crumbs = $fromMaterials ? ['Inventory' => null, 'Materials' => '/inventory/materials', 'Add' => null] : ['Setup' => null, 'Items' => '/items/', $isEdit ? 'Edit' : 'Add' => null];
$cancelUrl = $fromMaterials ? '/inventory/materials' : ($isEdit ? '/items/' . $id : '/items/');
$p = 'item-form';
$base = (string) ($item['base_unit_code'] ?? '');
$kind = items_unit_kind($item['item_class'] ?? null);
$qtySuffix = $base !== '' ? display_unit($base, $kind) : null;
$costSuffix = $base !== '' ? '$ per ' . display_unit($base, $kind) : null;
$tabs = ['basics' => 'Basics', 'control' => 'Control', 'planning' => 'Planning'];
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'item-form', 'crumbs' => $crumbs, 'actionsHtml' => form_actions('item-form', $cancelUrl, $fromMaterials ? 'Save Material' : 'Save Item')]) ?>
<div class="main-content" id="item-form-content">
    <form id="item-form" method="post" action="/items/save" hx-post="/items/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <?php if ($fromMaterials): ?><input type="hidden" name="return_to" value="materials" /><?php endif; ?>
        <?= view('shared/validation-errors.php', ['errors' => $errors, 'id' => 'item-form-errors']) ?>
        <div class="row"><div class="col-lg-12">
            <div class="card border-top-0" id="item-form-card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="item-form-tabs" role="tablist">
                        <?php foreach ($tabs as $key => $label): ?>
                        <li class="nav-item flex-fill border-top" role="presentation">
                            <a href="javascript:void(0);" id="item-form-tab-<?= e($key) ?>" class="nav-link<?= $key === 'basics' ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#item-form-pane-<?= e($key) ?>" role="tab"><?= e($label) ?></a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="item-form-pane-basics" role="tabpanel">
                        <div class="card-body">
                            <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Basics</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">What the item is and how it is stocked.</span></h5></div>
                            <?= form_input($p, 'code', 'Code', $item['code'] ?? '', $errors, ['required' => true, 'maxlength' => 40, 'icon' => 'feather-hash', 'autofocus' => !$isEdit]) ?>
                            <?= form_input($p, 'name', 'Name', $item['name'] ?? '', $errors, ['required' => true, 'maxlength' => 160, 'icon' => 'feather-package']) ?>
                            <?= form_select($p, 'item_class', 'Item class', $classes, $item['item_class'] ?? '', $errors, ['required' => true, 'blank' => 'Choose a class', 'help' => 'Classes are maintained under Setup, Item classes.']) ?>
                            <?= form_select($p, 'base_unit_code', 'Base unit', items_base_unit_options(), $base, $errors, ['required' => true, 'blank' => 'Choose a unit', 'help' => 'Stock is held in this unit.']) ?>
                            <?= form_input($p, 'units_per_case', 'Units per case', $item['units_per_case'] ?? '', $errors, ['type' => 'number', 'min' => 1, 'step' => 1, 'icon' => 'feather-box', 'help' => 'Finished goods only.']) ?>
                            <?= form_textarea($p, 'notes', 'Notes', $item['notes'] ?? '', $errors) ?>
                            <?= form_checkbox($p, 'active', 'Active', (bool) ($item['active'] ?? true), ['last' => true]) ?>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="item-form-pane-control" role="tabpanel">
                        <div class="card-body">
                            <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Control</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Lot control, receiving defaults, costing and TTB category.</span></h5></div>
                            <?= form_checkbox($p, 'lot_controlled', 'Lot controlled', (bool) ($item['lot_controlled'] ?? true)) ?>
                            <?= form_checkbox($p, 'catch_weight', 'Catch weight', (bool) ($item['catch_weight'] ?? false)) ?>
                            <?= form_input($p, 'shelf_life_days', 'Shelf life (days)', $item['shelf_life_days'] ?? '', $errors, ['type' => 'number', 'min' => 0, 'step' => 1, 'icon' => 'feather-calendar']) ?>
                            <?= form_select($p, 'default_receipt_status', 'Default receipt status', ITEM_RECEIPT_STATUSES, $item['default_receipt_status'] ?? 'released', $errors, ['required' => true]) ?>
                            <?= form_select($p, 'consumption_mode', 'Consumption mode', ITEM_CONSUMPTION_MODES, $item['consumption_mode'] ?? 'explicit', $errors, ['required' => true]) ?>
                            <?= form_select($p, 'costing_method', 'Costing method', ITEM_COSTING_METHODS, $item['costing_method'] ?? 'actual_lot', $errors, ['required' => true]) ?>
                            <?= form_input($p, 'standard_cost_per_base', 'Standard cost', $item['standard_cost_per_base'] ?? '', $errors, ['type' => 'number', 'min' => 0, 'step' => 'any', 'icon' => 'feather-dollar-sign', 'suffix' => $costSuffix, 'help' => 'Entered per display unit; stored per base unit.']) ?>
                            <?= form_select($p, 'ttb_material_category', 'TTB material category', ITEM_TTB_CATEGORIES, $item['ttb_material_category'] ?? 'none', $errors, ['required' => true, 'last' => true]) ?>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="item-form-pane-planning" role="tabpanel">
                        <div class="card-body">
                            <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Planning</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Reorder thresholds, entered in display units.</span></h5></div>
                            <?= form_input($p, 'reorder_point', 'Reorder point', $item['reorder_point'] ?? '', $errors, ['type' => 'number', 'min' => 0, 'step' => 'any', 'icon' => 'feather-trending-down', 'suffix' => $qtySuffix]) ?>
                            <?= form_input($p, 'min_qty', 'Minimum quantity', $item['min_qty'] ?? '', $errors, ['type' => 'number', 'min' => 0, 'step' => 'any', 'icon' => 'feather-arrow-down', 'suffix' => $qtySuffix]) ?>
                            <?= form_input($p, 'max_qty', 'Maximum quantity', $item['max_qty'] ?? '', $errors, ['type' => 'number', 'min' => 0, 'step' => 'any', 'icon' => 'feather-arrow-up', 'suffix' => $qtySuffix, 'last' => true]) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div></div>
    </form>
</div>
