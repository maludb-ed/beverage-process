<?php
/**
 * The supplier field of the purchase order form, with an inline "New supplier" panel (no modal): the panel posts on its own
 * to /purchase-orders/supplier-quick and this block comes back with the new supplier selected.
 * @var array $suppliers  id => name  @var mixed $selected  @var array $errors  @var ?array $newSupplier  panel values when open  @var array $newErrors
 */
$prefix = 'purchase-order-form';
$newSupplier = $newSupplier ?? null;
$newErrors = $newErrors ?? [];
$id = field_id($prefix, 'supplier_id');
$panel = $prefix . '-new-supplier';
$n = static fn(string $f) => 'new_supplier[' . $f . ']';
$nv = static fn(string $f, string $default = '') => (string) ($newSupplier[$f] ?? $default);
$nerr = static fn(string $f) => isset($newErrors[$f]) ? '<div class="invalid-feedback d-block">' . e($newErrors[$f]) . '</div>' : '';
$ninv = static fn(string $f) => isset($newErrors[$f]) ? ' is-invalid' : '';
?>
<div id="<?= e($prefix) ?>-supplier-block">
    <?= form_row_open($prefix, 'supplier_id', 'Supplier') ?>
        <div class="input-group">
            <select class="form-select<?= invalid_class($errors, 'supplier_id') ?>" id="<?= e($id) ?>" name="supplier_id" required>
                <option value="">Choose a supplier</option>
                <?php foreach ($suppliers as $sid => $name): ?><option value="<?= e($sid) ?>"<?= (string) $selected === (string) $sid ? ' selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?>
            </select>
            <button type="button" class="btn btn-light-brand" id="<?= e($prefix) ?>-new-supplier-btn" aria-controls="<?= e($panel) ?>"
                    hx-on:click="document.getElementById('<?= e($panel) ?>').classList.toggle('d-none'); document.getElementById('<?= e($panel) ?>-name').focus()"><i class="feather-plus me-1"></i>New supplier</button>
        </div>
        <?= isset($errors['supplier_id']) ? '<div class="invalid-feedback d-block">' . e($errors['supplier_id']) . '</div>' : '' ?>
    <?= form_row_close() ?>
    <div class="border rounded p-3 mb-4<?= $newSupplier === null ? ' d-none' : '' ?>" id="<?= e($panel) ?>">
        <div class="fw-semibold mb-1">New supplier</div>
        <div class="fs-12 text-muted mb-3">Added to the supplier list and selected for this order. Address, notes and the items they supply can be completed later on the supplier page.</div>
        <?= isset($newErrors['form']) ? '<div class="alert alert-danger" id="' . e($panel) . '-error">' . e($newErrors['form']) . '</div>' : '' ?>
        <div class="row g-3">
            <div class="col-12 col-md-8">
                <label class="fw-semibold fs-12" for="<?= e($panel) ?>-name">Name</label>
                <input type="text" maxlength="160" class="form-control<?= $ninv('name') ?>" id="<?= e($panel) ?>-name" name="<?= e($n('name')) ?>" value="<?= e($nv('name')) ?>" /><?= $nerr('name') ?>
            </div>
            <div class="col-12 col-md-4">
                <label class="fw-semibold fs-12" for="<?= e($panel) ?>-kind">Kind</label>
                <select class="form-select<?= $ninv('kind') ?>" id="<?= e($panel) ?>-kind" name="<?= e($n('kind')) ?>">
                    <?php foreach (SUPPLIER_KINDS as $k => $label): ?><option value="<?= e($k) ?>"<?= $nv('kind', 'vendor') === $k ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select><?= $nerr('kind') ?>
            </div>
            <div class="col-12 col-md-4">
                <label class="fw-semibold fs-12" for="<?= e($panel) ?>-contact">Contact</label>
                <input type="text" maxlength="120" class="form-control" id="<?= e($panel) ?>-contact" name="<?= e($n('contact_name')) ?>" value="<?= e($nv('contact_name')) ?>" />
            </div>
            <div class="col-12 col-md-5">
                <label class="fw-semibold fs-12" for="<?= e($panel) ?>-email">Email</label>
                <input type="email" maxlength="200" class="form-control<?= $ninv('email') ?>" id="<?= e($panel) ?>-email" name="<?= e($n('email')) ?>" value="<?= e($nv('email')) ?>" /><?= $nerr('email') ?>
            </div>
            <div class="col-12 col-md-3">
                <label class="fw-semibold fs-12" for="<?= e($panel) ?>-phone">Phone</label>
                <input type="tel" maxlength="40" class="form-control" id="<?= e($panel) ?>-phone" name="<?= e($n('phone')) ?>" value="<?= e($nv('phone')) ?>" />
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2 justify-content-end mt-3">
            <button type="button" class="btn btn-sm btn-light-brand" id="<?= e($panel) ?>-cancel-btn" hx-on:click="document.getElementById('<?= e($panel) ?>').classList.add('d-none')">Cancel</button>
            <button type="button" class="btn btn-sm btn-primary" id="<?= e($panel) ?>-save-btn" hx-post="/purchase-orders/supplier-quick" hx-target="#<?= e($prefix) ?>-supplier-block" hx-swap="outerHTML"
                    hx-include="#<?= e($panel) ?>, #<?= e($id) ?>"><i class="feather-check me-1"></i>Add supplier</button>
        </div>
    </div>
</div>
