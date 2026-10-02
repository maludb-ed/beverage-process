<?php /** @var array $input  @var array $errors  @var array $premises */
$p = 'standard-cost-overhead-form';
?>
<?= view('shared/page-header.php', ['title' => 'Set Overhead Rate', 'screen' => 'standard-cost-overhead-form', 'crumbs' => ['Products' => null, 'Standard costs' => '/standard-costs/', 'Overhead rate' => null], 'actionsHtml' => form_actions('standard-cost-overhead-form', '/standard-costs/', 'Save Rate')]) ?>
<div class="main-content" id="standard-cost-overhead-form-content">
    <form id="standard-cost-overhead-form" method="post" action="/standard-costs/overhead" hx-post="/standard-costs/overhead" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <div class="row"><div class="col-lg-12">
            <div class="card" id="standard-cost-overhead-form-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Overhead rate</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Cost added per <?= e(display_unit('L')) ?> of batch volume when a recipe version is activated.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'standard-cost-overhead-form-errors']) ?>
                    <?= form_select($p, 'premises', 'Premises', $premises, $input['premises_id'] ?? '', $errors, ['required' => true, 'blank' => count($premises) === 1 ? null : 'Choose a premises', 'name' => 'premises_id']) ?>
                    <?= form_input($p, 'rate', 'Rate per ' . display_unit('L'), $input['rate'] ?? '', $errors, ['type' => 'number', 'min' => 0, 'step' => 'any', 'required' => true, 'icon' => 'feather-dollar-sign']) ?>
                    <?= form_input($p, 'effective_from', 'Effective from', $input['effective_from'] ?? today(), $errors, ['type' => 'date', 'required' => true, 'icon' => 'feather-calendar', 'last' => true]) ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
