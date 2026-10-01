<?php /** @var array $supplier  @var array $errors */
$id = $supplier['id'] ?? null;
$isEdit = $id !== null;
$title = $isEdit ? 'Edit Supplier' : 'Add Supplier';
$p = 'supplier-form';
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'supplier-form', 'crumbs' => ['Setup' => null, 'Suppliers' => '/suppliers/', $isEdit ? 'Edit' : 'Add' => null], 'actionsHtml' => form_actions('supplier-form', $isEdit ? '/suppliers/' . $id : '/suppliers/', 'Save Supplier')]) ?>
<div class="main-content" id="supplier-form-content">
    <form id="supplier-form" method="post" action="/suppliers/save" hx-post="/suppliers/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <div class="row"><div class="col-lg-12">
            <div class="card stretch stretch-full" id="supplier-form-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Supplier</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Who we buy from and how to reach them.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => $errors, 'id' => 'supplier-form-errors']) ?>
                    <?= form_input($p, 'name', 'Name', $supplier['name'] ?? '', $errors, ['required' => true, 'maxlength' => 160, 'icon' => 'feather-truck', 'autofocus' => !$isEdit]) ?>
                    <?= form_select($p, 'kind', 'Kind', SUPPLIER_KINDS, $supplier['kind'] ?? 'vendor', $errors, ['required' => true]) ?>
                    <?= form_input($p, 'contact_name', 'Contact name', $supplier['contact_name'] ?? '', $errors, ['maxlength' => 120, 'icon' => 'feather-user']) ?>
                    <?= form_input($p, 'email', 'Email', $supplier['email'] ?? '', $errors, ['type' => 'email', 'maxlength' => 200, 'icon' => 'feather-mail']) ?>
                    <?= form_input($p, 'phone', 'Phone', $supplier['phone'] ?? '', $errors, ['type' => 'tel', 'maxlength' => 40, 'icon' => 'feather-phone']) ?>
                    <?= form_textarea($p, 'address', 'Address', $supplier['address'] ?? '', $errors) ?>
                    <?= form_textarea($p, 'notes', 'Notes', $supplier['notes'] ?? '', $errors) ?>
                    <?= form_checkbox($p, 'active', 'Active', (bool) ($supplier['active'] ?? true), ['last' => true]) ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
