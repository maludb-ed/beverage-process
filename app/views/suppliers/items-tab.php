<?php /** @var array $supplier  @var array $items  @var array $itemInput  @var array $itemErrors  @var bool $canEdit  @var array $itemOptions  @var array $unitOptions */
$id = (int) $supplier['id'];
$p = 'supplier-items-form';
$paneTarget = '#supplier-view-pane-items';
?>
<div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Items</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Items this supplier sells, with purchase unit and last price.</span></h5></div>
<?= view('shared/validation-errors.php', ['errors' => $itemErrors, 'id' => 'supplier-items-form-errors']) ?>
<div class="table-responsive mb-4">
    <table class="table table-hover mb-0" id="supplier-view-items-table">
        <thead class="thead-light">
            <tr>
                <th id="supplier-view-items-col-item">Item</th>
                <th id="supplier-view-items-col-supplier-sku">Supplier SKU</th>
                <th id="supplier-view-items-col-purchase-unit">Purchase unit</th>
                <th id="supplier-view-items-col-to-base-factor">Equivalent</th>
                <th id="supplier-view-items-col-last-price">Last price</th>
                <th id="supplier-view-items-col-lead-time">Lead time</th>
                <th id="supplier-view-items-col-active">Active</th>
            </tr>
        </thead>
        <tbody id="supplier-view-items-tbody">
            <?php if ($items === []): ?>
            <tr id="supplier-view-items-empty"><td colspan="7" class="text-center text-muted py-4">This supplier has no items yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($items as $row): $rid = (int) $row['id']; ?>
            <tr id="supplier-item-row-<?= e($rid) ?>">
                <td id="supplier-item-row-<?= e($rid) ?>-item"><a <?= nav_attrs('/items/' . (int) $row['item_id']) ?>><?= status_dot($row['active'] ? 'success' : 'secondary') ?><span><?= e($row['item_code']) ?></span></a> <small class="text-muted"><?= e($row['item_name']) ?></small></td>
                <td id="supplier-item-row-<?= e($rid) ?>-supplier-sku"><?= e($row['supplier_sku']) ?></td>
                <td id="supplier-item-row-<?= e($rid) ?>-purchase-unit"><?= e($row['purchase_unit_code']) ?></td>
                <td id="supplier-item-row-<?= e($rid) ?>-to-base-factor">1 <?= e($row['purchase_unit_code']) ?> = <?= e(items_plain_number($row['to_base_factor'])) ?> <?= e($row['base_unit_code']) ?></td>
                <td id="supplier-item-row-<?= e($rid) ?>-last-price"><?= $row['last_price'] === null ? '' : e('$' . number_format((float) $row['last_price'], 4) . ' / ' . $row['purchase_unit_code']) ?></td>
                <td id="supplier-item-row-<?= e($rid) ?>-lead-time"><?= $row['lead_time_days'] === null ? '' : e($row['lead_time_days'] . ' days') ?></td>
                <td id="supplier-item-row-<?= e($rid) ?>-active"><?= e(yes_no($row['active'])) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php if ($canEdit): ?>
<form id="supplier-items-form" method="post" action="/suppliers/<?= e($id) ?>/items/save" hx-post="/suppliers/<?= e($id) ?>/items/save" hx-target="<?= e($paneTarget) ?>" hx-swap="innerHTML">
    <?= csrf_field() ?>
    <h6 class="fw-bold mb-4" id="supplier-items-form-title">Add an item</h6>
    <?= form_select($p, 'item_id', 'Item', $itemOptions, $itemInput['item_id'] ?? '', $itemErrors, ['required' => true, 'blank' => 'Choose an item']) ?>
    <?= form_input($p, 'supplier_sku', 'Supplier SKU', $itemInput['supplier_sku'] ?? '', $itemErrors, ['maxlength' => 60, 'icon' => 'feather-hash']) ?>
    <?= form_select($p, 'purchase_unit_code', 'Purchase unit', $unitOptions, $itemInput['purchase_unit_code'] ?? '', $itemErrors, ['required' => true, 'blank' => 'Choose a unit']) ?>
    <?= form_input($p, 'to_base_factor', 'Base units per purchase unit', $itemInput['to_base_factor'] ?? '', $itemErrors, ['type' => 'number', 'min' => 0, 'step' => 'any', 'icon' => 'feather-repeat', 'help' => 'Leave blank to use the chosen unit\'s standard factor.']) ?>
    <?= form_input($p, 'last_price', 'Last price (per purchase unit)', $itemInput['last_price'] ?? '', $itemErrors, ['type' => 'number', 'min' => 0, 'step' => 'any', 'icon' => 'feather-dollar-sign']) ?>
    <?= form_input($p, 'lead_time_days', 'Lead time (days)', $itemInput['lead_time_days'] ?? '', $itemErrors, ['type' => 'number', 'min' => 0, 'step' => 1, 'icon' => 'feather-clock']) ?>
    <div class="text-end"><button type="submit" id="supplier-items-form-save-btn" class="btn btn-primary"><i class="feather-plus me-2"></i><span>Add item</span></button></div>
</form>
<?php endif; ?>
