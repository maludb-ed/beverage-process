<?php
/**
 * The customer field of the order and standing order forms, with an inline "New customer" panel (no modal):
 * the panel posts on its own to /orders/customer-quick and this block comes back with the new customer selected.
 * @var string $prefix  form id prefix ('order-form', 'standing-order-form')
 * @var array $customers  id => [name, default_destination]
 * @var mixed $selected  @var ?string $destination  the order's destination (null: the form has none)
 * @var array $errors  @var ?array $newCustomer  panel values when open  @var array $newErrors
 */
$newCustomer = $newCustomer ?? null;
$newErrors = $newErrors ?? [];
$destination = $destination ?? null;
$id = field_id($prefix, 'customer_id');
$panel = $prefix . '-new-customer';
$n = static fn(string $f) => 'new_customer[' . $f . ']';
$nv = static fn(string $f, string $default = '') => (string) ($newCustomer[$f] ?? $default);
$nerr = static fn(string $f) => isset($newErrors[$f]) ? '<div class="invalid-feedback d-block">' . e($newErrors[$f]) . '</div>' : '';
$ninv = static fn(string $f) => isset($newErrors[$f]) ? ' is-invalid' : '';
?>
<div id="<?= e($prefix) ?>-customer-block">
    <?= form_row_open($prefix, 'customer_id', 'Customer') ?>
        <div class="input-group">
            <select class="form-select<?= invalid_class($errors, 'customer_id') ?>" id="<?= e($id) ?>" name="customer_id" required>
                <option value="">Choose a customer</option>
                <?php foreach ($customers as $cid => $c): ?><option value="<?= e($cid) ?>"<?= (string) $selected === (string) $cid ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-light-brand" id="<?= e($prefix) ?>-new-customer-btn" aria-controls="<?= e($panel) ?>"
                    hx-on:click="document.getElementById('<?= e($panel) ?>').classList.toggle('d-none'); document.getElementById('<?= e($panel) ?>-name').focus()"><i class="feather-user-plus me-1"></i>New customer</button>
        </div>
        <?= isset($errors['customer_id']) ? '<div class="invalid-feedback d-block">' . e($errors['customer_id']) . '</div>' : '' ?>
    <?= form_row_close() ?>
    <div class="border rounded p-3 mb-4<?= $newCustomer === null ? ' d-none' : '' ?>" id="<?= e($panel) ?>">
        <div class="fw-semibold mb-1">New customer</div>
        <div class="fs-12 text-muted mb-3">Added to the customer list and selected for this order. Contact details and address can be completed later on the customer page.</div>
        <?= isset($newErrors['form']) ? '<div class="alert alert-danger" id="' . e($panel) . '-error">' . e($newErrors['form']) . '</div>' : '' ?>
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="fw-semibold fs-12" for="<?= e($panel) ?>-name">Name</label>
                <input type="text" maxlength="120" class="form-control<?= $ninv('name') ?>" id="<?= e($panel) ?>-name" name="<?= e($n('name')) ?>" value="<?= e($nv('name')) ?>" /><?= $nerr('name') ?>
            </div>
            <div class="col-6 col-md-3">
                <label class="fw-semibold fs-12" for="<?= e($panel) ?>-kind">Kind</label>
                <select class="form-select<?= $ninv('kind') ?>" id="<?= e($panel) ?>-kind" name="<?= e($n('kind')) ?>">
                    <?php foreach (CUSTOMER_KINDS as $k => $label): ?><option value="<?= e($k) ?>"<?= $nv('kind', 'retailer') === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select><?= $nerr('kind') ?>
            </div>
            <div class="col-6 col-md-3">
                <label class="fw-semibold fs-12" for="<?= e($panel) ?>-destination">Usually</label>
                <select class="form-select<?= $ninv('default_destination') ?>" id="<?= e($panel) ?>-destination" name="<?= e($n('default_destination')) ?>">
                    <?php foreach (CUSTOMER_DESTINATIONS as $k => $label): ?><option value="<?= e($k) ?>"<?= $nv('default_destination', 'tax_paid_sale') === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select><?= $nerr('default_destination') ?>
            </div>
            <div class="col-12 col-md-4">
                <label class="fw-semibold fs-12" for="<?= e($panel) ?>-contact">Contact</label>
                <input type="text" maxlength="120" class="form-control" id="<?= e($panel) ?>-contact" name="<?= e($n('contact_name')) ?>" value="<?= e($nv('contact_name')) ?>" />
            </div>
            <div class="col-12 col-md-4">
                <label class="fw-semibold fs-12" for="<?= e($panel) ?>-email">Email</label>
                <input type="email" maxlength="200" class="form-control<?= $ninv('email') ?>" id="<?= e($panel) ?>-email" name="<?= e($n('email')) ?>" value="<?= e($nv('email')) ?>" /><?= $nerr('email') ?>
            </div>
            <div class="col-6 col-md-2">
                <label class="fw-semibold fs-12" for="<?= e($panel) ?>-phone">Phone</label>
                <input type="tel" maxlength="40" class="form-control" id="<?= e($panel) ?>-phone" name="<?= e($n('phone')) ?>" value="<?= e($nv('phone')) ?>" />
            </div>
            <div class="col-6 col-md-2">
                <label class="fw-semibold fs-12" for="<?= e($panel) ?>-permit">TTB permit</label>
                <input type="text" maxlength="40" class="form-control<?= $ninv('permit_number') ?>" id="<?= e($panel) ?>-permit" name="<?= e($n('permit_number')) ?>" value="<?= e($nv('permit_number')) ?>" /><?= $nerr('permit_number') ?>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 justify-content-end mt-3">
            <button type="button" class="btn btn-sm btn-light-brand" id="<?= e($panel) ?>-cancel-btn" hx-on:click="document.getElementById('<?= e($panel) ?>').classList.add('d-none')">Cancel</button>
            <button type="button" class="btn btn-sm btn-primary" id="<?= e($panel) ?>-save-btn" hx-post="/orders/customer-quick" hx-target="#<?= e($prefix) ?>-customer-block" hx-swap="outerHTML"
                    hx-include="#<?= e($panel) ?>, #<?= e($id) ?><?= $destination !== null ? ', #' . e(field_id($prefix, 'destination_kind')) : '' ?>" hx-vals='<?= e(json_encode(['prefix' => $prefix, 'with_destination' => $destination !== null ? '1' : '0'])) ?>'><i class="feather-check me-1"></i>Add customer</button>
        </div>
    </div>
    <?php if ($destination !== null): ?>
        <?= form_select($prefix, 'destination_kind', 'Destination', ORDER_DESTINATIONS, $destination, $errors, ['required' => true, 'help' => 'Carried to the shipment. A customer added here, or an order started from a customer page, takes their usual destination.']) ?>
    <?php endif; ?>
</div>
