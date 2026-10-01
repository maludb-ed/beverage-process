<?php /** @var array $run  @var array $inputs  @var array $outputs  @var array $errors  @var array $inputErrors  @var array $outputErrors  @var array $fruitLots  @var array $outputItems  @var array $vessels  @var array $locations  @var array $pressVessels  @var array $premises */
$id = $run['id'] ?? null;
$isEdit = $id !== null;
$title = $isEdit ? 'Edit ' . ($run['number'] ?? 'Press Run') : 'Add Press Run';
$cancelUrl = $isEdit ? '/press-runs/' . $id : '/press-runs/';
$p = 'press-run-form';
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'press-run-form', 'crumbs' => ['Production' => null, 'Press runs' => '/press-runs/', $isEdit ? 'Edit' : 'Add' => null], 'actionsHtml' => form_actions('press-run-form', $cancelUrl, 'Save Press Run')]) ?>
<div class="main-content" id="press-run-form-content">
    <form id="press-run-form" method="post" action="/press-runs/save" hx-post="/press-runs/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <div class="row"><div class="col-lg-12">
            <div class="card stretch stretch-full" id="press-run-form-header-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Press</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Save as a draft, then post to issue the fruit and create the juice and pomace lots.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'press-run-form-errors']) ?>
                    <?= form_input($p, 'run_on', 'Date', $run['run_on'] ?? today(), $errors, ['type' => 'date', 'icon' => 'feather-calendar', 'required' => true]) ?>
                    <?= form_select($p, 'press_vessel', 'Press', $pressVessels, $run['press_vessel_id'] ?? '', $errors, ['name' => 'press_vessel_id', 'blank' => $pressVessels === [] ? 'No press vessels set up' : 'No press recorded']) ?>
                    <?php if (count($premises) === 1): ?>
                        <input type="hidden" id="press-run-form-field-premises" name="premises_id" value="<?= e(array_key_first($premises)) ?>" />
                    <?php else: ?>
                        <?= form_select($p, 'premises', 'Premises', $premises, $run['premises_id'] ?? '', $errors, ['name' => 'premises_id', 'required' => true, 'blank' => 'Choose a premises']) ?>
                    <?php endif; ?>
                    <?= form_input($p, 'started_at', 'Started', $run['started_at'] ?? '', $errors, ['type' => 'datetime-local', 'icon' => 'feather-clock']) ?>
                    <?= form_input($p, 'finished_at', 'Finished', $run['finished_at'] ?? '', $errors, ['type' => 'datetime-local', 'icon' => 'feather-clock', 'help' => 'The juice occupies its vessel from this time (now when blank).']) ?>
                    <?= form_textarea($p, 'notes', 'Notes', $run['notes'] ?? '', $errors, ['last' => true]) ?>
                </div>
            </div>
            <div class="card stretch stretch-full" id="press-run-form-inputs-card">
                <div class="card-header">
                    <h5 class="card-title">Fruit in</h5>
                    <button type="button" class="btn btn-sm btn-light-brand" id="press-run-form-add-input-btn"
                            hx-get="/press-runs/fruit-lot-options" hx-target="#press-run-form-inputs" hx-swap="beforeend" hx-vals='js:{n: "n" + Date.now()}'><i class="feather-plus me-1"></i>Add fruit lot</button>
                </div>
                <div class="card-body" id="press-run-form-inputs">
                    <?php foreach ($inputs as $n => $row): ?>
                        <?= view('press-runs/partials/input-row.php', ['n' => $n, 'row' => $row, 'fruitLots' => $fruitLots, 'rowErrors' => $inputErrors[$n] ?? []]) ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="card stretch stretch-full" id="press-run-form-outputs-card">
                <div class="card-header">
                    <h5 class="card-title">Juice and pomace out</h5>
                    <button type="button" class="btn btn-sm btn-light-brand" id="press-run-form-add-output-btn"
                            hx-get="/press-runs/output-row" hx-target="#press-run-form-outputs" hx-swap="beforeend" hx-vals='js:{n: "n" + Date.now()}'><i class="feather-plus me-1"></i>Add output</button>
                </div>
                <div class="card-body" id="press-run-form-outputs">
                    <?php foreach ($outputs as $n => $row): ?>
                        <?= view('press-runs/partials/output-row.php', ['n' => $n, 'row' => $row, 'outputItems' => $outputItems, 'vessels' => $vessels, 'locations' => $locations, 'rowErrors' => $outputErrors[$n] ?? []]) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
