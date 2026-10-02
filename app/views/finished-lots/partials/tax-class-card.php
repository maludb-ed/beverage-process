<?php /** @var array $lot  @var ?string $derived  @var array $limits  @var array $reasons  @var ?int $defaultReason  @var bool $canOverride  @var array $errors  @var array $input  @var ?string $notice */
$errors = $errors ?? [];
$input = $input ?? [];
$id = (int) $lot['lot_id'];
$p = 'finished-lot-view';
$abv = $lot['abv'] === null ? null : (float) $lot['abv'];
$co2 = $lot['co2_g_100ml'] === null ? null : (float) $lot['co2_g_100ml'];
$fruit = $lot['fruit_share_pct'] === null ? null : (float) $lot['fruit_share_pct'];
$margin = static fn(?float $value, float $limit, bool $under): string => $value === null ? '<span class="text-muted">not recorded</span>'
    : (($under ? $limit - $value : $value - $limit) >= 0 ? '<span class="text-success">' . e(number_format(abs($under ? $limit - $value : $value - $limit), 3)) . ' inside</span>' : '<span class="text-danger">' . e(number_format(abs($under ? $limit - $value : $value - $limit), 3)) . ' outside</span>');
?>
<div class="card" id="finished-lot-view-tax-class-card">
    <div class="card-header"><h5 class="card-title">Tax class check</h5><div><?= badge(humanize($lot['tax_class']), $lot['tax_class'] === 'hard_cider' ? 'success' : 'warning', 'finished-lot-view-tax-class-badge') ?> <small class="text-muted" id="finished-lot-view-tax-class-source"><?= e($lot['tax_class_source'] === 'override' ? 'Override: ' . ($lot['override_reason_name'] ?? '') . ($lot['override_by_name'] ? ', ' . $lot['override_by_name'] : '') : 'Derived') ?></small></div></div>
    <div class="card-body">
        <?php if (!empty($notice)): ?><div class="alert alert-success" id="finished-lot-view-tax-class-notice"><?= e($notice) ?></div><?php endif; ?>
        <div class="table-responsive mb-4">
            <table class="table mb-0" id="finished-lot-view-tax-class-table">
                <thead class="thead-light"><tr><th>Test</th><th>This lot</th><th>Hard cider limit</th><th>Margin</th></tr></thead>
                <tbody>
                    <tr id="finished-lot-view-check-co2"><td>CO2</td><td><?= $co2 === null ? '—' : e(number_format($co2, 3)) . ' g/100 mL' ?></td><td>at most <?= e(number_format($limits['co2_max'], 2)) ?> g/100 mL</td><td><?= $margin($co2, $limits['co2_max'], true) ?></td></tr>
                    <tr id="finished-lot-view-check-abv"><td>ABV</td><td><?= $abv === null ? '—' : e(number_format($abv, 2)) . ' %' ?></td><td>under <?= e(number_format($limits['abv_max'], 1)) ?> %</td><td><?= $margin($abv, $limits['abv_max'], true) ?></td></tr>
                    <tr id="finished-lot-view-check-fruit"><td>Fruit share</td><td><?= $fruit === null ? '—' : e(number_format($fruit, 1)) . ' %' ?></td><td>over <?= e(number_format($limits['fruit_min'], 0)) ?> %</td><td><?= $margin($fruit, $limits['fruit_min'], false) ?></td></tr>
                </tbody>
            </table>
        </div>
        <ul class="list-unstyled mb-0">
            <li class="hstack justify-content-between mb-3"><span class="text-muted">Other fruit</span><span id="finished-lot-view-flag-other-fruit"><?= yes_no($lot['contains_other_fruit']) ?></span></li>
            <li class="hstack justify-content-between mb-3"><span class="text-muted">Flavoring beyond the allowance</span><span id="finished-lot-view-flag-flavoring"><?= yes_no($lot['contains_flavoring']) ?></span></li>
            <li class="hstack justify-content-between mb-0"><span class="text-muted">Class the rules derive</span><span id="finished-lot-view-derived-class"><?= $derived !== null ? badge(humanize($derived), $derived === 'hard_cider' ? 'success' : 'warning') : '—' ?><?= $derived !== null && $derived !== $lot['tax_class'] ? ' <small class="text-danger">differs from the recorded class</small>' : '' ?></span></li>
        </ul>
    </div>
    <?php if ($canOverride): ?>
    <form class="p-4 border-top" id="finished-lot-view-tax-class-form" method="post" action="/finished-lots/<?= e($id) ?>/tax-class" hx-post="/finished-lots/<?= e($id) ?>/tax-class" hx-target="#finished-lot-view-tax-class-card" hx-swap="outerHTML" hx-confirm="Override the tax class of lot <?= e($lot['lot_number']) ?>? This changes the tax rate applied when it is removed.">
        <?= csrf_field() ?>
        <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'finished-lot-view-tax-class-errors']) ?>
        <div class="fw-semibold mb-3">Override tax class</div>
        <div class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="fw-semibold fs-12" for="finished-lot-view-field-tax-class">Tax class</label>
                <select class="form-select<?= isset($errors['tax_class']) ? ' is-invalid' : '' ?>" id="finished-lot-view-field-tax-class" name="tax_class" required>
                    <?php foreach (FINISHED_LOT_TAX_CLASSES as $value => $label): ?><option value="<?= e($value) ?>"<?= ($input['tax_class'] ?? $lot['tax_class']) === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="fw-semibold fs-12" for="finished-lot-view-field-reason-code-id">Reason</label>
                <select class="form-select<?= isset($errors['reason_code_id']) ? ' is-invalid' : '' ?>" id="finished-lot-view-field-reason-code-id" name="reason_code_id" required>
                    <?php foreach ($reasons as $value => $label): ?><option value="<?= e($value) ?>"<?= (int) ($input['reason_code_id'] ?? $defaultReason) === (int) $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="fw-semibold fs-12" for="finished-lot-view-field-note">Note</label>
                <input type="text" class="form-control" id="finished-lot-view-field-note" name="note" value="<?= e($input['note'] ?? '') ?>" maxlength="200" />
            </div>
            <div class="col-12 col-md-2"><button type="submit" class="btn btn-primary w-100" id="finished-lot-view-tax-class-save-btn"><i class="feather-check me-1"></i>Override</button></div>
        </div>
    </form>
    <?php endif; ?>
</div>
