<?php /** @var array $item  @var array $units  @var array $unitInput  @var array $unitErrors  @var bool $canEdit */
$id = (int) $item['id'];
$base = $item['base_unit_code'];
$p = 'item-units-form';
$paneTarget = '#item-view-pane-units';
?>
<div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Alternate units</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Item-specific units such as a bin or sack, each worth a fixed amount of <?= e($base) ?>.</span></h5></div>
<?= view('shared/validation-errors.php', ['errors' => $unitErrors, 'id' => 'item-units-form-errors']) ?>
<div class="table-responsive mb-4">
    <table class="table table-hover mb-0" id="item-view-units-table">
        <thead class="thead-light">
            <tr>
                <th id="item-view-units-col-unit-code">Unit code</th>
                <th id="item-view-units-col-unit-name">Unit name</th>
                <th id="item-view-units-col-to-base-factor">Equivalent</th>
                <th id="item-view-units-col-is-purchase-default">Purchase default</th>
                <th id="item-view-units-col-actions" class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody id="item-view-units-tbody">
            <?php if ($units === []): ?>
            <tr id="item-view-units-empty"><td colspan="5" class="text-center text-muted py-4">No alternate units yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($units as $unit): $uid = (int) $unit['id']; ?>
            <tr id="item-unit-row-<?= e($uid) ?>">
                <td id="item-unit-row-<?= e($uid) ?>-unit-code"><?= e($unit['unit_code']) ?></td>
                <td id="item-unit-row-<?= e($uid) ?>-unit-name"><?= e($unit['unit_name']) ?></td>
                <td id="item-unit-row-<?= e($uid) ?>-to-base-factor">1 <?= e($unit['unit_code']) ?> = <?= e(items_plain_number($unit['to_base_factor'])) ?> <?= e($base) ?></td>
                <td id="item-unit-row-<?= e($uid) ?>-is-purchase-default"><?= e(yes_no($unit['is_purchase_default'])) ?></td>
                <td id="item-unit-row-<?= e($uid) ?>-actions" class="text-end">
                    <?php if ($canEdit): ?>
                    <button type="button" id="item-unit-row-<?= e($uid) ?>-delete-btn" class="avatar-text avatar-md border-0 bg-transparent" data-bs-toggle="tooltip" title="Remove"
                        hx-post="/items/<?= e($id) ?>/units/delete" hx-vals='<?= e(json_encode(['unit_id' => $uid])) ?>' hx-target="<?= e($paneTarget) ?>" hx-swap="innerHTML"
                        hx-confirm="Remove the unit <?= e($unit['unit_code']) ?> from this item?"><i class="feather-trash-2"></i></button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php if ($canEdit): ?>
<form id="item-units-form" method="post" action="/items/<?= e($id) ?>/units/save" hx-post="/items/<?= e($id) ?>/units/save" hx-target="<?= e($paneTarget) ?>" hx-swap="innerHTML">
    <?= csrf_field() ?>
    <h6 class="fw-bold mb-4" id="item-units-form-title">Add a unit</h6>
    <?= form_input($p, 'unit_code', 'Unit code', $unitInput['unit_code'] ?? '', $unitErrors, ['required' => true, 'maxlength' => 20, 'icon' => 'feather-hash', 'placeholder' => 'bin']) ?>
    <?= form_input($p, 'unit_name', 'Unit name', $unitInput['unit_name'] ?? '', $unitErrors, ['required' => true, 'maxlength' => 60, 'icon' => 'feather-tag', 'placeholder' => 'Bin']) ?>
    <?= form_input($p, 'to_base_factor', 'Base units per 1 unit', $unitInput['to_base_factor'] ?? '', $unitErrors, ['type' => 'number', 'min' => 0, 'step' => 'any', 'required' => true, 'icon' => 'feather-repeat', 'suffix' => $base, 'help' => 'How many ' . $base . ' one unit holds.']) ?>
    <?= form_checkbox($p, 'is_purchase_default', 'Purchase default', (bool) ($unitInput['is_purchase_default'] ?? false)) ?>
    <div class="text-end"><button type="submit" id="item-units-form-save-btn" class="btn btn-primary"><i class="feather-plus me-2"></i><span>Add unit</span></button></div>
</form>
<?php endif; ?>
