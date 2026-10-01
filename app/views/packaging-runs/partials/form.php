<?php /** @var array $run  @var array $errors  @var array $materialErrors  @var ?array $batch  @var ?array $configuration  @var array $batches  @var array $configurations  @var array $vessels  @var array $locations  @var array $materials  @var array $readback */
$id = $run['id'] ?? null;
$isEdit = $id !== null;
$title = $isEdit ? 'Edit ' . ($run['number'] ?? 'Packaging Run') : 'Add Packaging Run';
$cancelUrl = $isEdit ? '/packaging-runs/' . $id : '/packaging-runs/';
$p = 'packaging-run-form';
$tabs = ['details' => 'Details', 'materials' => 'Materials'];
$when = static fn($value) => substr(str_replace(' ', 'T', (string) $value), 0, 16);
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'packaging-run-form', 'crumbs' => ['Packaging' => null, 'Packaging runs' => '/packaging-runs/', $isEdit ? 'Edit' : 'Add' => null], 'actionsHtml' => form_actions('packaging-run-form', $cancelUrl, 'Save Packaging Run')]) ?>
<div class="main-content" id="packaging-run-form-content">
    <form id="packaging-run-form" method="post" action="/packaging-runs/save" hx-post="/packaging-runs/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <?= view('shared/validation-errors.php', ['errors' => array_values(array_merge(array_filter(array_diff_key($errors, ['materials' => 1]), 'is_string'), array_values($materialErrors))), 'id' => 'packaging-run-form-errors']) ?>
        <div class="row"><div class="col-lg-12">
            <div class="card border-top-0" id="packaging-run-form-card">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs flex-wrap w-100 text-center customers-nav-tabs" id="packaging-run-form-tabs" role="tablist">
                        <?php foreach ($tabs as $key => $label): ?>
                        <li class="nav-item flex-fill border-top" role="presentation">
                            <a href="javascript:void(0);" id="packaging-run-form-tab-<?= e($key) ?>" class="nav-link<?= $key === 'details' ? ' active' : '' ?>" data-bs-toggle="tab" data-bs-target="#packaging-run-form-pane-<?= e($key) ?>" role="tab"><?= e($label) ?></a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="packaging-run-form-pane-details" role="tabpanel">
                        <div class="card-body">
                            <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Run</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Save as a draft, then post to create the finished lot and use up materials.</span></h5></div>
                            <?php if ($batches === []): ?>
                                <div class="alert alert-info" id="packaging-run-form-no-batches">No batch is ready for packaging. A batch must be active and in maturation, blend, back-sweeten, carbonate or package. <a <?= nav_attrs('/batches/') ?>>See batches</a>.</div>
                            <?php endif; ?>
                            <?= form_select($p, 'batch_id', 'Batch', $batches, $run['batch_id'] ?? '', $errors, [
                                'required' => true, 'blank' => 'Choose a batch',
                                'extra' => ' hx-get="/packaging-runs/options?part=batch" hx-trigger="change" hx-swap="none" hx-include="#packaging-run-form-field-units-out"',
                            ]) ?>
                            <div id="packaging-run-form-configuration-slot"><?= view('packaging-runs/partials/slot-configuration.php', ['configurations' => $configurations, 'run' => $run, 'errors' => $errors, 'batch' => $batch]) ?></div>
                            <?= form_input($p, 'run_on', 'Run date', $run['run_on'] ?? today(), $errors, ['type' => 'date', 'icon' => 'feather-calendar', 'required' => true]) ?>
                            <div id="packaging-run-form-vessel-slot"><?= view('packaging-runs/partials/slot-vessel.php', ['vessels' => $vessels, 'run' => $run, 'errors' => $errors, 'batch' => $batch]) ?></div>
                            <div id="packaging-run-form-location-slot"><?= view('packaging-runs/partials/slot-location.php', ['locations' => $locations, 'run' => $run, 'errors' => $errors, 'batch' => $batch]) ?></div>
                            <?= form_input($p, 'volume_in_gal', 'Volume in', $run['volume_in_gal'] ?? '', $errors, ['type' => 'number', 'step' => '0.1', 'min' => '0', 'icon' => 'feather-droplet', 'required' => true, 'suffix' => 'gal', 'help' => 'Taken from the source vessel.']) ?>
                            <?= form_input($p, 'units_out', 'Units out', $run['units_out'] ?? '', $errors, ['type' => 'number', 'step' => '1', 'min' => '1', 'icon' => 'feather-package', 'help' => 'Kegs, cans or bottles filled. Required to post.']) ?>
                            <?= form_input($p, 'abv_at_packaging', 'ABV at packaging', $run['abv_at_packaging'] ?? '', $errors, ['type' => 'number', 'step' => '0.01', 'min' => '0', 'max' => '25', 'icon' => 'feather-percent', 'suffix' => '%', 'help' => 'Required to post.']) ?>
                            <?= form_input($p, 'co2_g_100ml', 'CO2', $run['co2_g_100ml'] ?? '', $errors, ['type' => 'number', 'step' => '0.001', 'min' => '0', 'max' => '2', 'icon' => 'feather-wind', 'suffix' => 'g/100 mL', 'help' => 'Required to post. Hard cider is 0.64 or less.']) ?>
                            <?= form_input($p, 'started_at', 'Started at', $when($run['started_at'] ?? ''), $errors, ['type' => 'datetime-local', 'icon' => 'feather-clock']) ?>
                            <?= form_input($p, 'finished_at', 'Finished at', $when($run['finished_at'] ?? ''), $errors, ['type' => 'datetime-local', 'icon' => 'feather-clock']) ?>
                            <?= form_textarea($p, 'notes', 'Notes', $run['notes'] ?? '', $errors) ?>
                            <div id="packaging-run-form-readback" hx-get="/packaging-runs/options?part=readback" hx-swap="innerHTML" hx-include="#packaging-run-form"
                                 hx-trigger="change from:#packaging-run-form-field-units-out, change from:#packaging-run-form-field-volume-in-gal, change from:#packaging-run-form-field-abv-at-packaging, change from:#packaging-run-form-field-co2-g-100ml">
                                <?= view('packaging-runs/partials/readback.php', ['readback' => $readback, 'batch' => $batch, 'configuration' => $configuration]) ?>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="packaging-run-form-pane-materials" role="tabpanel">
                        <div class="card-body">
                            <div class="mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                <h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Materials</span><span class="fs-12 fw-normal text-muted">From the package bill of materials. Edit a quantity or choose a lot.</span></h5>
                                <button type="button" class="btn btn-sm btn-light-brand" id="packaging-run-form-materials-recalc-btn" hx-get="/packaging-runs/options?part=materials" hx-swap="none"
                                        hx-include="#packaging-run-form-field-batch-id, #packaging-run-form-field-packaging-configuration-id, #packaging-run-form-field-units-out"><i class="feather-refresh-cw me-1"></i>Recalculate from units</button>
                            </div>
                            <div id="packaging-run-form-materials"><?= view('packaging-runs/partials/materials.php', ['materials' => $materials, 'materialErrors' => $materialErrors, 'configuration' => $configuration]) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div></div>
    </form>
</div>
