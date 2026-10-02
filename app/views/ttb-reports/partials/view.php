<?php /** @var array $report  @var array $lines  @var array $map  @var array $user  @var ?string $filedError */
$id = (int) $report['id'];
$status = $report['status'];
$canEdit = user_can($user, 'compliance');
$totals = $report['totals'];
$cells = [];
foreach ($lines as $line) {
    $cells[$line['section']][$line['line_code']][(string) ($line['tax_class'] ?? '')] = $line;
}
$classes = $totals['classes'] ?? [];
if ($classes === []) {   // reports generated before classes were recorded
    $present = array_unique(array_map(static fn($l) => (string) ($l['tax_class'] ?? ''), array_filter($lines, static fn($l) => $l['section'] !== 'IV')));
    $classes = array_values(array_filter(TTB_TAX_CLASS_ORDER, static fn($c) => in_array($c, $present, true)));
}
$periodLabel = format_date($report['period_start']) . ' to ' . format_date($report['period_end']);
$actions = '';
if ($canEdit && $status === 'draft') {
    $actions .= '<button type="button" class="btn btn-light-brand" id="ttb-report-view-regenerate-btn" hx-post="/ttb-reports/' . e($id) . '/regenerate" hx-target="#page-content" hx-swap="innerHTML"><i class="feather-refresh-cw me-2"></i><span>Regenerate</span></button>';
    $actions .= '<button type="button" class="btn btn-primary" id="ttb-report-view-finalize-btn" hx-post="/ttb-reports/' . e($id) . '/finalize" hx-target="#page-content" hx-swap="innerHTML" hx-confirm="Finalize ' . e($report['number']) . ' for ' . e($periodLabel) . '? The numbers freeze and can no longer be regenerated."><i class="feather-lock me-2"></i><span>Finalize</span></button>';
}
if ($canEdit && $status === 'final') {
    $now = (new DateTimeImmutable('now', new DateTimeZone((string) config('app.timezone'))))->format('Y-m-d\TH:i');
    $actions .= '<form class="d-flex gap-2" id="ttb-report-view-filed-form" hx-post="/ttb-reports/' . e($id) . '/filed" hx-target="#page-content" hx-swap="innerHTML">'
        . '<input type="datetime-local" class="form-control' . ($filedError ? ' is-invalid' : '') . '" name="filed_at" id="ttb-report-view-field-filed-at" value="' . e($now) . '" max="' . e($now) . '" aria-label="Filed at" title="Filed at" required />'
        . '<button type="submit" class="btn btn-primary text-nowrap" id="ttb-report-view-filed-btn" hx-confirm="Mark ' . e($report['number']) . ' filed with TTB at the time entered?"><i class="feather-send me-2"></i><span>Mark filed</span></button></form>';
}
$item = static fn(string $key, string $icon, string $label, string $html, bool $last = false): string =>
    '<li class="hstack justify-content-between ' . ($last ? 'mb-0' : 'mb-4') . '"><span class="text-muted fw-medium hstack gap-3"><i class="' . e($icon) . '"></i>' . e($label) . '</span><span id="ttb-report-view-' . e($key) . '" class="text-end">' . $html . '</span></li>';
$differences = [];
foreach ($totals['reconciliation'] ?? [] as $section => $byClass) {
    foreach ($byClass as $class => $r) {
        if (abs((float) $r['difference_gal']) >= 0.005) {
            $differences[] = ['section' => $section, 'class' => $class, 'computed' => (float) $r['computed_gal'], 'physical' => (float) $r['physical_gal'], 'difference' => (float) $r['difference_gal']];
        }
    }
}
?>
<?= view('shared/page-header.php', ['title' => $report['number'], 'screen' => 'ttb-report-view', 'crumbs' => ['Compliance' => null, 'TTB reports' => '/ttb-reports/', $report['number'] => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="ttb-report-view-content">
    <?php if ($filedError): ?><div class="alert alert-danger" id="ttb-report-view-filed-error"><?= e($filedError) ?></div><?php endif; ?>
    <?php if ($differences !== []): ?>
        <div class="alert alert-warning" id="ttb-report-view-reconciliation-warning">
            <div class="fw-semibold mb-1"><i class="feather-alert-triangle me-2"></i>Closing balance does not match the physical balance</div>
            <ul class="mb-0 ps-3 fs-12">
                <?php foreach ($differences as $d): ?>
                    <li>Section <?= e($d['section']) ?>, <?= e($d['class'] === 'unclassified' ? 'unclassified' : (TAX_CLASS_LABELS[$d['class']] ?? humanize($d['class']))) ?>: computed <?= e(number_format($d['computed'], 2)) ?> gal, physical <?= e(number_format($d['physical'], 2)) ?> gal, difference <?= e(number_format($d['difference'], 2)) ?> gal.</li>
                <?php endforeach; ?>
            </ul>
            <div class="fs-11 mt-1">Physical bulk is batch volume in vessels; physical bottled is finished-lot units in bonded locations. A difference means an activity the form lines do not capture; record it before finalizing.</div>
        </div>
    <?php endif; ?>
    <div class="row">
        <div class="col-xxl-4 col-xl-6">
            <div class="card" id="ttb-report-view-summary">
                <div class="card-body">
                    <div class="mb-4 d-flex align-items-center justify-content-between">
                        <div><h5 class="fw-bold mb-1"><?= e($report['number']) ?></h5><div class="fs-12 text-muted">TTB F <?= e($report['form_code']) ?></div></div>
                        <?= status_badge($status, 'ttb-report-view-status') ?>
                    </div>
                    <ul class="list-unstyled mb-0">
                        <?= $item('premises', 'feather-home', 'Premises', e($report['premises_name']) . ($report['registry_number'] ? ' <small class="text-muted d-block">' . e($report['registry_number']) . '</small>' : '')) ?>
                        <?= $item('period', 'feather-calendar', 'Period', e($periodLabel)) ?>
                        <?= $item('generated', 'feather-cpu', 'Generated', e(format_datetime($report['generated_at']) . ($report['generated_by_name'] ? ', ' . $report['generated_by_name'] : ''))) ?>
                        <?= $item('finalized', 'feather-lock', 'Finalized', e(format_datetime($report['finalized_at']) ?: 'Not yet')) ?>
                        <?= $item('filed', 'feather-send', 'Filed', e($report['filed_at'] ? format_datetime($report['filed_at']) . ($report['filed_by_name'] ? ', ' . $report['filed_by_name'] : '') : 'Not yet'), true) ?>
                    </ul>
                </div>
            </div>
            <div class="card" id="ttb-report-view-totals">
                <div class="card-header"><h5 class="card-title">Tax totals</h5></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0" id="ttb-report-view-totals-table">
                            <thead class="thead-light"><tr><th>Tax class</th><th class="text-end">Gallons</th><th class="text-end">Rate</th><th class="text-end">Credit</th><th class="text-end">Tax</th></tr></thead>
                            <tbody>
                            <?php foreach ($totals['tax_class'] ?? [] as $class => $t): ?>
                                <tr id="ttb-report-view-totals-row-<?= e(str_replace('_', '-', $class)) ?>">
                                    <td><?= e(TAX_CLASS_LABELS[$class] ?? humanize($class)) ?></td>
                                    <td class="text-end"><?= e(number_format((float) $t['gallons'], 2)) ?></td>
                                    <td class="text-end"><?= $t['rate'] === null ? '<span class="text-danger">none</span>' : '$' . e(number_format((float) $t['rate'], 3)) ?></td>
                                    <td class="text-end">$<?= e(number_format((float) $t['credit'], 3)) ?></td>
                                    <td class="text-end"><?= $t['tax'] === null ? '' : '$' . e(number_format((float) $t['tax'], 2)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (($totals['tax_class'] ?? []) === []): ?><tr id="ttb-report-view-totals-empty"><td colspan="5" class="text-center text-muted py-4">No tax-determined removals in this period.</td></tr><?php endif; ?>
                            </tbody>
                            <tfoot><tr class="fw-semibold" id="ttb-report-view-totals-total"><td>Total</td><td class="text-end"><?= e(number_format((float) ($totals['total_gallons'] ?? 0), 2)) ?></td><td></td><td></td><td class="text-end">$<?= e(number_format((float) ($totals['total_tax'] ?? 0), 2)) ?></td></tr></tfoot>
                        </table>
                    </div>
                    <p class="fs-11 text-muted px-3 py-2 mb-0">Tax-determined gallons are lines B8, B8t and B12. Tax = gallons × (rate − CBMA credit, <?= e(humanize((string) ($totals['cbma_tier'] ?? $report['cbma_tier']))) ?>). These are the numbers for the 5000.24 excise return; returns to bond (B4) are claimed separately.</p>
                </div>
            </div>
        </div>
        <div class="col-xxl-8 col-xl-6">
            <div class="card border-top-0" id="ttb-report-view-tabs-card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="ttb-report-view-tabs" role="tablist">
                        <?php foreach (['a' => 'A · Bulk', 'b' => 'B · Bottled', 'iv' => 'IV · Materials'] as $tab => $label): ?>
                            <li class="nav-item flex-fill border-top" role="presentation"><a href="javascript:void(0);" id="ttb-report-view-tab-<?= e($tab) ?>" class="nav-link<?= $tab === 'a' ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#ttb-report-view-pane-<?= e($tab) ?>" role="tab"><?= e($label) ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="tab-content">
                    <?php foreach (['A', 'B', 'IV'] as $section): $tab = strtolower($section); ?>
                        <div class="tab-pane fade<?= $section === 'A' ? ' show active' : '' ?>" id="ttb-report-view-pane-<?= e($tab) ?>" role="tabpanel">
                            <div class="px-3 pt-3 fs-12 fw-semibold" id="ttb-report-view-section-<?= e($tab) ?>-title"><?= e(TTB_SECTIONS[$section]) ?></div>
                            <?= view('ttb-reports/partials/section.php', ['reportId' => $id, 'section' => $section, 'mapRows' => $map[$section] ?? [], 'cells' => $cells[$section] ?? [], 'classes' => $classes]) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="offcanvas offcanvas-start" tabindex="-1" id="ttb-report-view-drilldown" aria-labelledby="ttb-report-view-drilldown-label">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="ttb-report-view-drilldown-label">Records behind the number</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close" id="ttb-report-view-drilldown-close-btn"></button>
    </div>
    <div class="offcanvas-body" id="ttb-report-view-drilldown-body"><p class="text-muted fs-12">Loading…</p></div>
</div>
