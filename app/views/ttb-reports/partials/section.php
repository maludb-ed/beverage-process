<?php /** @var int $reportId  @var string $section  @var array $mapRows  @var array $cells (line_code => class => line)  @var array $classes */
$key = strtolower($section);
$isMaterials = $section === 'IV';
$columns = $isMaterials ? ['' => 'Quantity'] : array_combine($classes, array_map(static fn($c) => $c === '' ? 'Unclassified' : (TAX_CLASS_LABELS[$c] ?? humanize($c)), $classes));
$fmt = static fn(float $v): string => number_format($v, 2);
$cell = static function (?array $line, string $id) use ($reportId, $fmt): string {
    if ($line === null) {
        return '<span class="text-muted">0.00</span>';
    }
    $value = (float) $line['value'];
    $ids = json_decode((string) $line['source_ids'], true) ?: [];
    if (abs($value) < 0.005 && $ids === []) {
        return '<span class="text-muted">' . e($fmt($value)) . '</span>';
    }
    $url = '/ttb-reports/' . $reportId . '/line/' . (int) $line['id'];
    return '<a href="javascript:void(0);" id="' . e($id) . '" hx-get="' . e($url) . '" hx-target="#ttb-report-view-drilldown-body" hx-swap="innerHTML"'
        . ' data-bs-toggle="offcanvas" data-bs-target="#ttb-report-view-drilldown" aria-controls="ttb-report-view-drilldown">' . e($fmt($value)) . '</a>';
};
?>
<div class="table-responsive">
    <table class="table table-hover table-sm mb-0" id="ttb-report-view-section-<?= e($key) ?>-table">
        <thead class="thead-light">
            <tr>
                <th>Line</th>
                <?php foreach ($columns as $class => $label): ?><th class="text-end" id="ttb-report-view-section-<?= e($key) ?>-col-<?= e($class === '' ? ($isMaterials ? 'quantity' : 'unclassified') : str_replace('_', '-', $class)) ?>"><?= e($label) ?></th><?php endforeach; ?>
                <?php if (!$isMaterials): ?><th class="text-end">Total</th><?php endif; ?>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($mapRows as $row): $code = (string) $row['line_code']; $rid = 'ttb-report-view-' . $key . '-line-' . $code; $total = 0.0;
            $isBalance = $row['source'] === 'balance'; ?>
            <tr id="<?= e($rid) ?>"<?= $isBalance ? ' class="fw-semibold"' : '' ?>>
                <td><span class="text-muted me-2"><?= e($code) ?></span><?= e($row['label']) ?><?= $isMaterials ? ' <small class="text-muted">(' . e(match ($row['match']['ttb_material_category'] ?? '') { 'fruit' => 'tons', 'sugar' => 'lb', default => 'gal' }) . ')</small>' : '' ?></td>
                <?php foreach ($columns as $class => $label): $line = $cells[$code][$class] ?? null; $total += (float) ($line['value'] ?? 0); ?>
                    <td class="text-end" id="<?= e($rid) ?>-<?= e($class === '' ? ($isMaterials ? 'quantity' : 'unclassified') : str_replace('_', '-', $class)) ?>"><?= $cell($line, $rid . '-link-' . ($class === '' ? 'x' : str_replace('_', '-', $class))) ?></td>
                <?php endforeach; ?>
                <?php if (!$isMaterials): ?><td class="text-end fw-semibold" id="<?= e($rid) ?>-total"><?= e($fmt($total)) ?></td><?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="fs-11 text-muted px-3 py-2 mb-0"><?= $isMaterials ? 'Fruit in tons (kg ÷ 907.18474), juice in gallons, sugar in pounds, from posted receipts of items with that TTB material category.' : 'Wine gallons. Select a number to see the records behind it.' ?><?= !$isMaterials && $classes === [] ? ' No tax class has activity in this period.' : '' ?></p>
