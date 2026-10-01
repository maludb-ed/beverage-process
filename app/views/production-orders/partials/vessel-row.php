<?php
/** @var int|string $n  @var array $row  @var array $rowErrors  @var array $vesselCatalog  @var float|null $volumeL */
$p = 'production-order-form-vessel-row-' . $n;
$fp = 'production-order-form-field-vessel-' . $n;
$rowErrors = $rowErrors ?? [];
$field = static fn(string $name) => 'vessels[' . $n . '][' . $name . ']';
$err = static fn(string $name) => isset($rowErrors[$name]) ? '<div class="invalid-feedback d-block">' . e($rowErrors[$name]) . '</div>' : '';
$inv = static fn(string $name) => isset($rowErrors[$name]) ? ' is-invalid' : '';
$vesselId = (int) ($row['vessel_id'] ?? 0);
$capacity = $vesselCatalog[$vesselId]['capacity_l'] ?? null;
$tooSmall = $capacity !== null && ($volumeL ?? null) !== null && $capacity < $volumeL;
?>
<div class="po-vessel-row border rounded p-3 mb-3" id="<?= e($p) ?>">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-md-4">
            <label class="fw-semibold fs-12" for="<?= e($fp) ?>-vessel">Vessel</label>
            <select class="form-select<?= $inv('vessel_id') ?>" id="<?= e($fp) ?>-vessel" name="<?= e($field('vessel_id')) ?>"
                    hx-get="/production-orders/vessel-row" hx-trigger="change" hx-target="#<?= e($p) ?>" hx-swap="outerHTML"
                    hx-include="#<?= e($p) ?>, #production-order-form-field-planned-volume-gal" hx-vals='{"n": "<?= e($n) ?>"}'>
                <option value="">Choose a vessel</option>
                <?php foreach ($vesselCatalog as $vid => $vessel): ?>
                    <option value="<?= e($vid) ?>"<?= $vesselId === $vid ? ' selected' : '' ?>><?= e($vessel['label']) ?></option>
                <?php endforeach; ?>
            </select><?= $err('vessel_id') ?>
        </div>
        <div class="col-12 col-md-2">
            <label class="fw-semibold fs-12" for="<?= e($fp) ?>-role">Role</label>
            <select class="form-select<?= $inv('role') ?>" id="<?= e($fp) ?>-role" name="<?= e($field('role')) ?>">
                <?php foreach (PRODUCTION_ORDER_VESSEL_ROLES as $code => $label): ?>
                    <option value="<?= e($code) ?>"<?= ($row['role'] ?? 'primary') === $code ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select><?= $err('role') ?>
        </div>
        <div class="col-6 col-md-3">
            <label class="fw-semibold fs-12" for="<?= e($fp) ?>-from">From</label>
            <input type="date" class="form-control<?= $inv('planned_from') ?>" id="<?= e($fp) ?>-from" name="<?= e($field('planned_from')) ?>" value="<?= e($row['planned_from'] ?? '') ?>" /><?= $err('planned_from') ?>
        </div>
        <div class="col-6 col-md-3">
            <label class="fw-semibold fs-12" for="<?= e($fp) ?>-to">To</label>
            <input type="date" class="form-control<?= $inv('planned_to') ?>" id="<?= e($fp) ?>-to" name="<?= e($field('planned_to')) ?>" value="<?= e($row['planned_to'] ?? '') ?>" /><?= $err('planned_to') ?>
        </div>
    </div>
    <?php if ($tooSmall): ?>
        <div class="text-warning fs-12 mt-2" id="<?= e($p) ?>-capacity-warning"><i class="feather-alert-triangle me-1"></i>This vessel holds <?= e(fmt_qty($capacity, 'L')) ?>, less than the planned <?= e(fmt_qty($volumeL, 'L')) ?>.</div>
    <?php endif; ?>
    <div class="text-end mt-2">
        <button type="button" class="btn btn-sm btn-light-brand" id="<?= e($p) ?>-remove-btn" hx-on:click="this.closest('.po-vessel-row').remove()"><i class="feather-trash-2 me-1"></i>Remove vessel</button>
    </div>
</div>
