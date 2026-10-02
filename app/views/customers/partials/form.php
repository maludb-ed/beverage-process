<?php /** @var array $customer  @var array $errors */
$id = $customer['id'] ?? null;
$isEdit = $id !== null;
$title = $isEdit ? 'Edit ' . ($customer['name'] ?? 'Customer') : 'Add Customer';
$cancelUrl = $isEdit ? '/customers/' . $id : '/customers/';
$p = 'customer-form';
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'customer-form', 'crumbs' => ['Compliance' => null, 'Customers' => '/customers/', $isEdit ? 'Edit' : 'Add' => null], 'actionsHtml' => form_actions('customer-form', $cancelUrl, 'Save Customer')]) ?>
<div class="main-content" id="customer-form-content">
    <form id="customer-form" method="post" action="/customers/save" hx-post="/customers/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <div class="row"><div class="col-lg-12">
            <div class="card" id="customer-form-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Customer</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Where finished goods go. The default destination preselects the removal kind.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'customer-form-errors']) ?>
                    <?= form_input($p, 'name', 'Name', $customer['name'] ?? '', $errors, ['required' => true, 'maxlength' => 120, 'icon' => 'feather-user', 'autofocus' => !$isEdit]) ?>
                    <?= form_select($p, 'kind', 'Kind', CUSTOMER_KINDS, $customer['kind'] ?? 'distributor', $errors, ['required' => true]) ?>
                    <?= form_select($p, 'default_destination', 'Default destination', CUSTOMER_DESTINATIONS, $customer['default_destination'] ?? 'tax_paid_sale', $errors, ['required' => true, 'help' => 'An in-bond transfer needs the consignee\'s permit number.']) ?>
                    <?= form_input($p, 'permit_number', 'Permit number', $customer['permit_number'] ?? '', $errors, ['maxlength' => 40, 'icon' => 'feather-hash', 'placeholder' => 'BWN-XX-12345', 'help' => 'Required for in-bond transfers.']) ?>
                    <?= form_input($p, 'contact_name', 'Contact', $customer['contact_name'] ?? '', $errors, ['maxlength' => 120, 'icon' => 'feather-user']) ?>
                    <?= form_input($p, 'email', 'Email', $customer['email'] ?? '', $errors, ['type' => 'email', 'maxlength' => 200, 'icon' => 'feather-mail']) ?>
                    <?= form_input($p, 'phone', 'Phone', $customer['phone'] ?? '', $errors, ['type' => 'tel', 'maxlength' => 40, 'icon' => 'feather-phone']) ?>
                    <?= form_textarea($p, 'address', 'Address', $customer['address'] ?? '', $errors) ?>
                    <?= form_textarea($p, 'notes', 'Notes', $customer['notes'] ?? '', $errors) ?>
                    <?= form_checkbox($p, 'active', 'Active', (bool) ($customer['active'] ?? true), ['last' => true]) ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
