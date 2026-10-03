<?php /** @var array $class  @var array $errors */
$id = $class['id'] ?? null;
$isEdit = $id !== null;
$builtin = !empty($class['is_builtin']);
$title = $isEdit ? 'Edit Item Class' : 'Add Item Class';
$p = 'item-class-form';
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'item-class-form', 'crumbs' => ['Setup' => null, 'Item classes' => '/item-classes/', $isEdit ? 'Edit' : 'Add' => null], 'actionsHtml' => form_actions('item-class-form', '/item-classes/', 'Save Item Class')]) ?>
<div class="main-content" id="item-class-form-content">
    <form id="item-class-form" method="post" action="/item-classes/save" hx-post="/item-classes/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <div class="row"><div class="col-lg-12">
            <div class="card" id="item-class-form-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Item class</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Classes group items: materials show under Inventory, Materials; finished product under Inventory, Finished product.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => $errors, 'id' => 'item-class-form-errors']) ?>
                    <?php if ($builtin): ?>
                        <div class="alert alert-info fs-12" id="item-class-form-builtin-note"><i class="feather-info me-2"></i>Built-in class: the code and kind are fixed because the application relies on them. The name, flags, order and active state can change.</div>
                    <?php endif; ?>
                    <?= form_input($p, 'code', 'Code', $class['code'] ?? '', $errors, ['required' => true, 'maxlength' => 30, 'icon' => 'feather-hash', 'autofocus' => !$isEdit, 'readonly' => $builtin, 'help' => 'Lowercase letters, digits and underscores, for example spice or barrel_aging.']) ?>
                    <?= form_input($p, 'name', 'Name', $class['name'] ?? '', $errors, ['required' => true, 'maxlength' => 80, 'icon' => 'feather-tag']) ?>
                    <?php if ($builtin): ?>
                        <input type="hidden" name="kind" value="<?= e($class['kind']) ?>" />
                        <?= form_input($p, 'kind_label', 'Kind', ITEM_CLASS_KINDS[$class['kind']] ?? $class['kind'], $errors, ['readonly' => true, 'icon' => 'feather-layers']) ?>
                    <?php else: ?>
                        <?= form_select($p, 'kind', 'Kind', ITEM_CLASS_KINDS, $class['kind'] ?? 'material', $errors, ['required' => true, 'help' => 'Which On hand screen lists the items.']) ?>
                    <?php endif; ?>
                    <?= form_checkbox($p, 'purchasable', 'Purchasable', (bool) ($class['purchasable'] ?? true), ['help' => 'Offered on purchase orders and receipts.']) ?>
                    <?= form_checkbox($p, 'recipe_ingredient', 'Recipe ingredient', (bool) ($class['recipe_ingredient'] ?? false), ['help' => 'Offered on recipe lines and batch additions.']) ?>
                    <?= form_input($p, 'display_order', 'Display order', $class['display_order'] ?? 100, $errors, ['type' => 'number', 'min' => 0, 'step' => 1, 'required' => true, 'icon' => 'feather-list']) ?>
                    <?= form_textarea($p, 'notes', 'Notes', $class['notes'] ?? '', $errors) ?>
                    <?= form_checkbox($p, 'active', 'Active', (bool) ($class['active'] ?? true), ['last' => true, 'help' => 'Inactive classes are hidden when adding items but existing items keep them.']) ?>
                </div>
                <?php if ($isEdit && !$builtin): ?>
                    <div class="card-footer text-end" id="item-class-form-footer">
                        <button type="button" class="btn btn-light-brand" id="item-class-form-delete-btn" hx-post="/item-classes/<?= e($id) ?>/delete" hx-target="#page-content" hx-swap="innerHTML" hx-confirm="Delete item class <?= e($class['name'] ?? $class['code']) ?>? Only possible while no item uses it."><i class="feather-trash-2 me-2"></i><span>Delete</span></button>
                    </div>
                <?php endif; ?>
            </div>
        </div></div>
    </form>
</div>
