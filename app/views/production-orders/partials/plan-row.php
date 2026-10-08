<?php
/** @var int|string $n  @var array $row  @var array $rowErrors  @var array $groups  @var array $catalog  @var float|null $volumeL  @var bool $allowShare */
$p = 'production-order-form-plan-row-' . $n;
$fp = 'production-order-form-field-plan-' . $n;
$rowErrors = $rowErrors ?? [];
$field = static fn(string $name) => 'plan[' . $n . '][' . $name . ']';
$err = static fn(string $name) => isset($rowErrors[$name]) ? '<div class="invalid-feedback d-block">' . e($rowErrors[$name]) . '</div>' : '';
$inv = static fn(string $name) => isset($rowErrors[$name]) ? ' is-invalid' : '';
$resource = $catalog[$row['resource'] ?? ''] ?? null;
$capacity = $resource !== null && $resource['resource_kind'] === 'vessel' ? (float) $resource['capacity_l'] : null;
$tooSmall = $capacity !== null && ($volumeL ?? null) !== null && $capacity < $volumeL;
$resourceKind = $resource['resource_kind'] ?? 'vessel';
$roleOrder = RESERVATION_ROLES_BY_RESOURCE[$resourceKind];
$roles = [];
foreach ($roleOrder as $code) { $roles[$code] = RESERVATION_ROLES[$code]; }
foreach (RESERVATION_ROLES as $code => $label) { $roles[$code] = $label; }
$selectedRole = ($row['role'] ?? '') !== '' ? $row['role'] : $roleOrder[0];
$allDay = (bool) ($row['all_day'] ?? true);
$clashes = $row['clash_rows'] ?? [];
?>
<div class="po-plan-row border rounded p-3 mb-3<?= isset($rowErrors['clashes']) ? ' border-warning' : '' ?>" id="<?= e($p) ?>">
    <?php if (!empty($row['id'])): ?><input type="hidden" name="<?= e($field('id')) ?>" value="<?= e($row['id']) ?>" /><?php endif; ?>
    <div class="row g-3 align-items-end">
        <div class="col-12 col-md-4">
            <label class="fw-semibold fs-12" for="<?= e($fp) ?>-resource">Vessel or equipment</label>
            <select class="form-select<?= $inv('resource') ?>" id="<?= e($fp) ?>-resource" name="<?= e($field('resource')) ?>"
                    hx-get="/production-orders/plan-row" hx-trigger="change" hx-target="#<?= e($p) ?>" hx-swap="outerHTML"
                    hx-include="#<?= e($p) ?>, #production-order-form-field-planned-volume-gal" hx-vals='{"n": "<?= e($n) ?>"}'>
                <option value="">Choose one</option>
                <?php foreach ($groups as $groupLabel => $options): ?>
                    <optgroup label="<?= e($groupLabel) ?>">
                    <?php foreach ($options as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= ($row['resource'] ?? '') === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select><?= $err('resource') ?>
        </div>
        <div class="col-12 col-md-2">
            <label class="fw-semibold fs-12" for="<?= e($fp) ?>-role">Role</label>
            <select class="form-select<?= $inv('role') ?>" id="<?= e($fp) ?>-role" name="<?= e($field('role')) ?>">
                <?php foreach ($roles as $code => $label): ?>
                    <option value="<?= e($code) ?>"<?= $selectedRole === $code ? ' selected' : '' ?>><?= e($label) ?></option>
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
    <div class="row g-3 align-items-end mt-0">
        <div class="col-12 col-md-4">
            <div class="form-check form-switch mt-2">
                <input type="hidden" name="<?= e($field('all_day')) ?>" value="0" />
                <input class="form-check-input" type="checkbox" role="switch" id="<?= e($fp) ?>-all-day" name="<?= e($field('all_day')) ?>" value="1"<?= $allDay ? ' checked' : '' ?>
                       hx-on:change="document.getElementById('<?= e($p) ?>-times').classList.toggle('d-none', this.checked)" />
                <label class="form-check-label fs-12" for="<?= e($fp) ?>-all-day">All day</label>
            </div>
        </div>
        <div class="col-12 col-md-8<?= $allDay ? ' d-none' : '' ?>" id="<?= e($p) ?>-times">
            <div class="row g-3">
                <div class="col-6">
                    <label class="fw-semibold fs-12" for="<?= e($fp) ?>-start-time">Start time</label>
                    <input type="time" class="form-control" id="<?= e($fp) ?>-start-time" name="<?= e($field('start_time')) ?>" value="<?= e($row['start_time'] ?? '') ?>" />
                </div>
                <div class="col-6">
                    <label class="fw-semibold fs-12" for="<?= e($fp) ?>-end-time">End time</label>
                    <input type="time" class="form-control" id="<?= e($fp) ?>-end-time" name="<?= e($field('end_time')) ?>" value="<?= e($row['end_time'] ?? '') ?>" />
                </div>
            </div>
        </div>
    </div>
    <?php if ($tooSmall): ?>
        <div class="text-warning fs-12 mt-2" id="<?= e($p) ?>-capacity-warning"><i class="feather-alert-triangle me-1"></i>This vessel holds <?= e(fmt_qty($capacity, 'L')) ?>, less than the planned <?= e(fmt_qty($volumeL, 'L')) ?>.</div>
    <?php endif; ?>
    <?php if ($clashes !== []): ?>
        <div class="fs-12 mt-2 text-warning" id="<?= e($p) ?>-clashes">
            <i class="feather-alert-triangle me-1"></i><?= isset($rowErrors['clashes']) ? e($rowErrors['clashes']) : 'Shared with:' ?>
            <ul class="mb-0 mt-1 text-body">
                <?php foreach (reservation_clash_labels($resource['name'] ?? 'This resource', $clashes) as $i => $line): ?><li id="<?= e($p) ?>-clash-<?= e($i) ?>"><?= e($line) ?></li><?php endforeach; ?>
            </ul>
            <?php if ($allowShare): ?>
            <div class="form-check mt-2">
                <input type="hidden" name="<?= e($field('share')) ?>" value="0" />
                <input class="form-check-input" type="checkbox" id="<?= e($fp) ?>-share" name="<?= e($field('share')) ?>" value="1"<?= !empty($row['share']) ? ' checked' : '' ?> />
                <label class="form-check-label fw-semibold text-body" for="<?= e($fp) ?>-share">Book anyway (shared use)</label>
            </div>
            <?php endif; ?>
        </div>
    <?php elseif (!empty($row['share'])): ?>
        <input type="hidden" name="<?= e($field('share')) ?>" value="1" />
    <?php endif; ?>
    <div class="text-end mt-2">
        <button type="button" class="btn btn-sm btn-light-brand" id="<?= e($p) ?>-remove-btn" hx-on:click="this.closest('.po-plan-row').remove()"><i class="feather-trash-2 me-1"></i>Remove</button>
    </div>
</div>
