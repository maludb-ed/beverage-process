<?php /** @var array $input  @var array $errors  @var array $targets */
$p = 'sensory-form';
$kind = $input['target_kind'] === 'lot' ? 'lot' : 'batch';
?>
<?= view('shared/page-header.php', ['title' => 'Record sensory panel', 'screen' => 'sensory-form', 'crumbs' => ['Quality' => null, 'Sensory' => '/sensory/', 'Record panel' => null], 'actionsHtml' => form_actions('sensory-form', '/sensory/', 'Save Panel')]) ?>
<div class="main-content" id="sensory-form-content">
    <form id="sensory-form" method="post" action="/sensory/save" hx-post="/sensory/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <div class="row"><div class="col-lg-12">
            <div class="card stretch stretch-full" id="sensory-form-card">
                <div class="card-body">
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'sensory-form-errors']) ?>
                    <?= view('lab/partials/target-kind.php', ['prefix' => $p, 'kind' => $kind]) ?>
                    <?= view('lab/partials/target-block.php', ['prefix' => $p, 'kind' => $kind, 'input' => $input, 'errors' => $errors, 'targets' => $targets, 'stages' => [], 'stageDefault' => '']) ?>
                    <?= form_input($p, 'panel_on', 'Panel date', $input['panel_on'] ?? '', $errors, ['type' => 'date', 'required' => true, 'icon' => 'feather-calendar']) ?>
                    <?= form_input($p, 'panelist_name', 'Panelist', $input['panelist_name'] ?? '', $errors, ['maxlength' => 120, 'icon' => 'feather-user', 'help' => 'Change the name to record a guest panelist.']) ?>
                    <?= form_input($p, 'sample_code', 'Sample code', $input['sample_code'] ?? '', $errors, ['maxlength' => 60, 'icon' => 'feather-hash']) ?>
                    <?= form_select($p, 'verdict', 'Verdict', SENSORY_VERDICTS, $input['verdict'] ?? '', $errors, ['required' => true, 'blank' => 'Choose…']) ?>
                    <?php foreach (SENSORY_ATTRIBUTES as $key => $label): $v = $input['attributes'][$key] ?? ''; $aid = $p . '-attr-' . $key; ?>
                        <div class="row mb-4 align-items-center" id="<?= e($aid) ?>-row">
                            <div class="col-lg-4"><label id="<?= e($aid) ?>-label" for="<?= e($aid) ?>" class="fw-semibold"><?= e($label) ?> (1 to 5):</label></div>
                            <div class="col-lg-8">
                                <input type="range" class="form-range" min="1" max="5" step="1" id="<?= e($aid) ?>" name="attributes[<?= e($key) ?>]" value="<?= e($v === '' ? 3 : $v) ?>"<?= $v === '' ? ' disabled' : '' ?> />
                                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="<?= e($aid) ?>-on"<?= $v === '' ? '' : ' checked' ?>
                                    onchange="document.getElementById('<?= e($aid) ?>').disabled = !this.checked" /><label class="form-check-label fs-12 text-muted" for="<?= e($aid) ?>-on">Score this attribute</label></div>
                                <?= isset($errors['attr_' . $key]) ? '<div class="invalid-feedback d-block">' . e($errors['attr_' . $key]) . '</div>' : '' ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?= form_row_open($p, 'faults', 'Faults') ?>
                        <div id="<?= e($p) ?>-field-faults">
                        <?php foreach (SENSORY_FAULTS as $key => $label): $on = array_key_exists($key, $input['faults'] ?? []); $fid = $p . '-fault-' . str_replace('_', '-', $key); ?>
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <div class="form-check flex-grow-1"><input class="form-check-input" type="checkbox" id="<?= e($fid) ?>" name="faults[<?= e($key) ?>]" value="1"<?= $on ? ' checked' : '' ?> />
                                    <label class="form-check-label" for="<?= e($fid) ?>"><?= e($label) ?></label></div>
                                <select class="form-select form-select-sm w-auto" id="<?= e($fid) ?>-intensity" name="fault_intensity[<?= e($key) ?>]" aria-label="<?= e($label) ?> intensity">
                                    <?php foreach ([1 => 'Slight', 2 => 'Moderate', 3 => 'Strong'] as $n => $name): ?><option value="<?= e($n) ?>"<?= ((int) ($input['faults'][$key] ?? 1)) === $n ? ' selected' : '' ?>><?= e($n . ' ' . $name) ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <?= isset($errors['fault_' . $key]) ? '<div class="invalid-feedback d-block">' . e($errors['fault_' . $key]) . '</div>' : '' ?>
                        <?php endforeach; ?>
                        </div>
                    <?= form_row_close() ?>
                    <?= form_textarea($p, 'comment', 'Comment', $input['comment'] ?? '', $errors, ['last' => true]) ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
