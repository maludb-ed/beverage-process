<?php /** @var array $sources  @var array $line */
$classLabel = $line['tax_class'] === null ? ($line['section'] === 'IV' ? '' : 'Unclassified') : (TAX_CLASS_LABELS[$line['tax_class']] ?? humanize($line['tax_class']));
$unit = TTB_UNIT_LABELS[$line['unit']] ?? $line['unit'];
$isBalance = in_array($sources['kind'], ['bulk_balance', 'bottled_balance'], true);
$sum = array_sum(array_map(static fn($r) => (float) $r['qty'], $sources['rows']));
?>
<div id="ttb-report-view-drilldown-content">
    <h6 class="fw-bold mb-1" id="ttb-report-view-drilldown-title"><?= e($line['section'] . ' line ' . $line['line_code'] . ': ' . $line['label']) ?></h6>
    <div class="fs-12 text-muted mb-3"><?= e(trim($classLabel . ' · ' . number_format((float) $line['value'], 2) . ' ' . $unit, ' ·')) ?></div>
    <?php if ($isBalance): ?>
        <p class="fs-12 text-muted"><?= e($sources['kind'] === 'bulk_balance' ? 'Batch volumes in vessels at the boundary.' : 'Finished lots in bonded locations at the boundary.') ?>
            <?= ($line['line_code'] === '31' || $line['line_code'] === '18') ? 'The line value is the computed closing balance; these rows are the physical balance (' . e(number_format($sum, 2)) . ' ' . e($unit) . ').' : '' ?></p>
    <?php endif; ?>
    <div class="table-responsive">
        <table class="table table-sm mb-0" id="ttb-report-view-drilldown-table">
            <thead class="thead-light"><tr><th>Date</th><th>Document</th><th><?= $sources['kind'] === 'removal' ? 'Lots' : 'Lot or batch' ?></th><th class="text-end"><?= e(ucfirst($unit)) ?></th></tr></thead>
            <tbody>
            <?php foreach ($sources['rows'] as $i => $r): ?>
                <tr id="ttb-report-view-drilldown-row-<?= e($i) ?>">
                    <td class="text-nowrap"><?= e(format_date($r['date'])) ?></td>
                    <td><?= $r['document_url'] ? '<a ' . nav_attrs($r['document_url']) . ' data-bs-dismiss="offcanvas">' . e($r['document']) . '</a>' : e($r['document']) ?><?= !empty($r['note']) ? ' <small class="text-muted d-block">' . e($r['note']) . '</small>' : '' ?></td>
                    <td><?= $r['subject_url'] ? '<a ' . nav_attrs($r['subject_url']) . ' data-bs-dismiss="offcanvas">' . e($r['subject']) . '</a>' : e($r['subject'] ?? '') ?></td>
                    <td class="text-end"><?= e(number_format((float) $r['qty'], $line['unit'] === 'ton' ? 3 : 2)) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($sources['rows'] === []): ?><tr id="ttb-report-view-drilldown-empty"><td colspan="4" class="text-center text-muted py-4">No source records for this cell.</td></tr><?php endif; ?>
            </tbody>
            <?php if ($sources['rows'] !== []): ?>
                <tfoot><tr class="fw-semibold"><td colspan="3">Total</td><td class="text-end" id="ttb-report-view-drilldown-total"><?= e(number_format($sum, $line['unit'] === 'ton' ? 3 : 2)) ?></td></tr></tfoot>
            <?php endif; ?>
        </table>
    </div>
</div>
