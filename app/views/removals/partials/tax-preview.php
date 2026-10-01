<?php /** @var ?array $tax  @var bool $determined  @var string $destination  @var string $prefix  (optional) @var ?array $stored posted removal */
$stored = $stored ?? null;
$classes = $tax['classes'] ?? [];
?>
<div id="<?= e($prefix) ?>-readback">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <span class="fw-semibold"><?= e(REMOVAL_DESTINATIONS[$destination] ?? humanize($destination)) ?></span>
        <?= $determined ? badge('Tax determined', 'warning', $prefix . '-determined') : badge('No tax determined', 'secondary', $prefix . '-determined') ?>
    </div>
    <?php if ($classes === []): ?>
        <p class="text-muted fs-12 mb-0" id="<?= e($prefix) ?>-empty">Add finished lots and units to see the wine gallons and tax.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm mb-0" id="<?= e($prefix) ?>-table">
                <thead class="thead-light"><tr><th>Tax class</th><th class="text-end">Units</th><th class="text-end">Wine gal</th><th class="text-end">Rate</th><th class="text-end">CBMA credit</th><th class="text-end">Tax</th></tr></thead>
                <tbody>
                <?php foreach ($classes as $class => $row): ?>
                    <tr id="<?= e($prefix) ?>-row-<?= e(str_replace('_', '-', $class)) ?>">
                        <td><?= e(TAX_CLASS_LABELS[$class] ?? humanize($class)) ?></td>
                        <td class="text-end"><?= e($row['units']) ?></td>
                        <td class="text-end" data-bs-toggle="tooltip" title="<?= e(number_format($row['liters'], 3)) ?> L"><?= e(number_format($row['gallons'], 4)) ?></td>
                        <td class="text-end"><?= $row['rate'] === null ? '<span class="text-danger">none</span>' : '$' . e(number_format($row['rate'], 3)) ?></td>
                        <td class="text-end">$<?= e(number_format($row['credit'], 3)) ?></td>
                        <td class="text-end"><?= $determined && $row['tax'] !== null ? '$' . e(number_format($row['tax'], 2)) : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="fw-semibold" id="<?= e($prefix) ?>-total">
                        <td>Total</td><td></td>
                        <td class="text-end"><?= e(number_format((float) ($stored['wine_gallons'] ?? $tax['total_gallons']), 4)) ?></td>
                        <td></td><td></td>
                        <td class="text-end"><?= $determined ? '$' . e(number_format((float) ($stored['tax_amount'] ?? $tax['total_tax']), 2)) : '—' ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="fs-11 text-muted mt-2 mb-0">Wine gallons = liters ÷ 3.785411784. Tax = gallons × (rate − CBMA credit) at the premises tier (<?= e(humanize($tax['cbma_tier'])) ?>), rates from the tax class rules in effect on the removal date.</p>
    <?php endif; ?>
</div>
