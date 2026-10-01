<?php /** @var array $input  @var array $errors  @var array $types  @var array $targets  @var array $stages  @var string $stageDefault */
$p = 'lab-reading-form';
$kind = $input['target_kind'] === 'lot' ? 'lot' : 'batch';
$typeOptions = array_column($types, 'name', 'code');
$type = $types[$input['measurement_type_code'] ?? ''] ?? null;
$step = $type ? number_format(10 ** -((int) $type['decimals']), (int) $type['decimals'], '.', '') : 'any';
?>
<?= view('shared/page-header.php', ['title' => 'Record reading', 'screen' => 'lab-reading-form', 'crumbs' => ['Quality' => null, 'Lab' => '/lab/', 'Record reading' => null], 'actionsHtml' => form_actions('lab-reading-form', '/lab/', 'Save Reading')]) ?>
<div class="main-content" id="lab-reading-form-content">
    <form id="lab-reading-form" method="post" action="/lab/save" hx-post="/lab/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <div class="row"><div class="col-lg-12">
            <div class="card stretch stretch-full" id="lab-reading-form-card">
                <div class="card-body">
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'lab-reading-form-errors']) ?>
                    <?= view('lab/partials/target-kind.php', ['prefix' => $p, 'kind' => $kind]) ?>
                    <?= view('lab/partials/target-block.php', ['prefix' => $p, 'kind' => $kind, 'input' => $input, 'errors' => $errors, 'targets' => $targets, 'stages' => $stages, 'stageDefault' => $stageDefault]) ?>
                    <?= form_select($p, 'measurement_type_code', 'Measurement', $typeOptions, $input['measurement_type_code'] ?? '', $errors, ['required' => true, 'blank' => 'Choose…']) ?>
                    <?= form_input($p, 'value', 'Value', $input['value'] ?? '', $errors, ['type' => 'number', 'step' => $step, 'required' => true, 'icon' => 'feather-activity', 'inputmode' => 'decimal',
                        'help' => $type ? $type['unit'] . ', valid range ' . (float) $type['min_valid'] . ' to ' . (float) $type['max_valid'] : 'Pick a measurement to see the valid range.']) ?>
                    <?= form_input($p, 'taken_at', 'Taken at', $input['taken_at'] ?? '', $errors, ['type' => 'datetime-local', 'required' => true, 'icon' => 'feather-clock']) ?>
                    <?= form_input($p, 'method', 'Method', $input['method'] ?? '', $errors, ['maxlength' => 200, 'icon' => 'feather-tool']) ?>
                    <?= form_textarea($p, 'note', 'Note', $input['note'] ?? '', $errors, ['last' => true]) ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
