<?php /** @var array $trace */
$form = '<form class="d-flex flex-wrap gap-2 align-items-center" id="trace-form" method="get" action="/trace/" hx-get="/trace/" hx-target="#trace-results" hx-swap="outerHTML">'
    . '<input type="search" class="form-control" name="lot_number" id="trace-field-lot-number" value="' . e($trace['lot_number']) . '" placeholder="Lot number" aria-label="Lot number" list="trace-lot-number-options" autocomplete="off"'
    . ' hx-get="/trace/suggest" hx-trigger="input changed delay:300ms" hx-target="#trace-lot-number-options" hx-swap="innerHTML" hx-vals=\'{"field": "lot"}\' hx-include="this" />'
    . '<datalist id="trace-lot-number-options"></datalist>'
    . '<input type="search" class="form-control" name="batch_number" id="trace-field-batch-number" value="' . e($trace['batch_number']) . '" placeholder="Batch number" aria-label="Batch number" list="trace-batch-number-options" autocomplete="off"'
    . ' hx-get="/trace/suggest" hx-trigger="input changed delay:300ms" hx-target="#trace-batch-number-options" hx-swap="innerHTML" hx-vals=\'{"field": "batch"}\' hx-include="this" />'
    . '<datalist id="trace-batch-number-options"></datalist>'
    . '<button type="submit" class="btn btn-primary text-nowrap" id="trace-submit-btn"><i class="feather-git-branch me-2"></i><span>Trace</span></button>'
    . '</form>';
?>
<?= view('shared/page-header.php', ['title' => 'Trace', 'screen' => 'trace', 'crumbs' => ['Compliance' => null, 'Trace' => null], 'actionsHtml' => $form]) ?>
<div class="main-content" id="trace-content">
    <div class="row">
        <?= view('trace/partials/results.php', ['trace' => $trace]) ?>
    </div>
</div>
