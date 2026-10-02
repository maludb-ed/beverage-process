<?php /** @var array $report  @var array $premises  @var array $errors */
$p = 'ttb-report-form';
$kind = $report['period_kind'];
$years = [];
for ($y = (int) substr(today(), 0, 4); $y >= (int) substr(today(), 0, 4) - 5; $y--) { $years[(string) $y] = (string) $y; }
$refresh = ' hx-get="/ttb-reports/new" hx-trigger="change" hx-target="#page-content" hx-swap="innerHTML" hx-include="#ttb-report-form" hx-vals=\'{"refresh": "1"}\'';
?>
<?= view('shared/page-header.php', ['title' => 'Generate TTB report', 'screen' => 'ttb-report-form', 'crumbs' => ['Compliance' => null, 'TTB reports' => '/ttb-reports/', 'Generate' => null], 'actionsHtml' => form_actions('ttb-report-form', '/ttb-reports/', 'Generate report')]) ?>
<div class="main-content" id="ttb-report-form-content">
    <form id="ttb-report-form" method="post" action="/ttb-reports/save" hx-post="/ttb-reports/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <div class="row"><div class="col-lg-12">
            <div class="card" id="ttb-report-form-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Report of Wine Premises Operations (5120.17)</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Generated from the ledger, losses, removals and receipts. Generating a period that exists opens that report.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'ttb-report-form-errors']) ?>
                    <?php if ($premises === []): ?>
                        <?= form_static($p, 'premises_id', 'Premises', '<span class="text-warning">No active premises files form 5120.17.</span>') ?>
                    <?php else: ?>
                        <?= form_select($p, 'premises_id', 'Premises', $premises, $report['premises_id'] ?? '', $errors, ['required' => true]) ?>
                    <?php endif; ?>
                    <?= form_select($p, 'period_kind', 'Period kind', PERIOD_KINDS, $kind, $errors, ['required' => true, 'extra' => $refresh, 'help' => 'Defaults to the premises filing frequency.']) ?>
                    <?php if ($kind === 'quarter'): ?>
                        <?= form_select($p, 'period', 'Quarter', ttb_reports_quarter_options(today()), $report['period'], $errors, ['required' => true, 'last' => true]) ?>
                    <?php elseif ($kind === 'year'): ?>
                        <?= form_select($p, 'period', 'Year', $years, $report['period'], $errors, ['required' => true, 'last' => true]) ?>
                    <?php else: ?>
                        <?= form_input($p, 'period', 'Month', $report['period'], $errors, ['type' => 'month', 'icon' => 'feather-calendar', 'required' => true, 'last' => true, 'max' => substr(today(), 0, 7), 'placeholder' => 'YYYY-MM']) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
