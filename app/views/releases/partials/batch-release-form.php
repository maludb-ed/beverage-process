<?php /** @var array $batch  @var array $readings  @var array $sensory  @var array $qc_targets  @var ?string $stage_instructions  @var int $failing_count  @var ?array $last_decision
         @var array $input  @var array $errors  @var array $reasons  @var ?int $defaultReasonId */
$id = (int) $batch['id'];
$p = 'batch-release-form';
$toStatus = $input['to_status'] ?? 'released';
$forced = $failing_count > 0 && $toStatus === 'released';
$isOverride = !empty($input['is_override']) || $forced;
$confirmText = 'Release with an out-of-spec reading on record?';
$fmt = static fn($v, $d) => number_format((float) $v, (int) $d, '.', ',');
$range = static fn($min, $max, $d) => $min !== null && $max !== null ? $fmt($min, $d) . ' to ' . $fmt($max, $d) : ($min !== null ? 'min ' . $fmt($min, $d) : ($max !== null ? 'max ' . $fmt($max, $d) : ''));
$actions = '<a id="batch-release-form-cancel-btn" class="btn btn-light-brand" ' . nav_attrs('/releases/') . '><i class="feather-x me-2"></i><span>Cancel</span></a>'
    . '<button type="submit" form="batch-release-form" id="batch-release-form-save-btn" class="btn btn-primary"><i class="feather-check me-2"></i><span>Record decision</span></button>';
$sync = "var f=document.getElementById('batch-release-form');var o=document.getElementById('batch-release-form-field-is-override');var s=document.getElementById('batch-release-form-field-to-status');"
    . "if(s.value==='released'&&f.dataset.failing>0){o.checked=true;}"
    . "if(o.checked){f.setAttribute('hx-confirm','" . e($confirmText) . "');}else{f.removeAttribute('hx-confirm');}";
?>
<?= view('shared/page-header.php', ['title' => 'Release ' . $batch['number'], 'screen' => 'batch-release-form', 'crumbs' => ['Quality' => null, 'Release queue' => '/releases/', $batch['number'] => null, 'Release' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="batch-release-form-content">
    <form id="batch-release-form" method="post" action="/batches/<?= e($batch['id']) ?>/release" hx-post="/batches/<?= e($batch['id']) ?>/release" hx-target="#page-content" hx-swap="innerHTML" data-failing="<?= e($failing_count) ?>"<?= $isOverride ? ' hx-confirm="' . e($confirmText) . '"' : '' ?>>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= e($id) ?>" />
        <div class="row">
            <div class="col-xxl-4 col-xl-6">
                <div class="card" id="batch-release-form-summary">
                    <div class="card-header"><h5 class="card-title">Batch</h5></div>
                    <div class="card-body">
                        <?= detail_row('batch-release-form-batch', 'Batch', e($batch['number'])) ?>
                        <?= detail_row('batch-release-form-product', 'Product', e($batch['product_name'])) ?>
                        <?= detail_row('batch-release-form-stage', 'Stage', e($batch['stage_name']) . ' <small class="text-muted">since ' . e(format_datetime($batch['entered_at'])) . '</small>') ?>
                        <?= detail_row('batch-release-form-volume', 'Volume', fmt_qty_html($batch['current_volume_l'], 'L')) ?>
                        <?= detail_row('batch-release-form-failing', 'Failing readings', $failing_count === 0 ? badge('None', 'success') : badge((string) $failing_count, 'danger')) ?>
                        <?= detail_row('batch-release-form-last-decision', 'Last decision', $last_decision ? status_badge($last_decision['to_status']) . ' <small class="text-muted">' . e(format_datetime($last_decision['decided_at'])) . ' by ' . e($last_decision['decided_by_name']) . '</small>' : '<span class="text-muted">None</span>', true) ?>
                    </div>
                </div>
            </div>
            <div class="col-xxl-8 col-xl-6">
                <div class="card" id="batch-release-form-card">
                    <div class="card-header"><h5 class="card-title">Decision</h5></div>
                    <div class="card-body">
                        <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'batch-release-form-errors']) ?>
                        <?= form_select($p, 'to_status', 'Decision', BATCH_RELEASE_STATUSES, $toStatus, $errors, ['required' => true, 'extra' => ' onchange="' . $sync . '"']) ?>
                        <?= form_select($p, 'basis', 'Basis', BATCH_RELEASE_BASES, $input['basis'] ?? ($readings !== [] ? 'readings' : 'inspection'), $errors, ['required' => true]) ?>
                        <?= form_row_open($p, 'is_override', 'Override') ?>
                            <div class="form-check form-switch"><input type="hidden" name="is_override" value="0" />
                                <input class="form-check-input" type="checkbox" role="switch" id="<?= e(field_id($p, 'is_override')) ?>" name="is_override" value="1"<?= $isOverride ? ' checked' : '' ?> onchange="<?= $sync ?>" /></div>
                        <?= form_row_close($failing_count > 0 ? 'Releasing with a failed reading is an override: it needs a reason and a note.' : 'Use when releasing against the evidence; a reason and note are required.') ?>
                        <?= form_select($p, 'reason_code_id', 'Override reason', $reasons, $input['reason_code_id'] ?? $defaultReasonId ?? '', $errors, ['blank' => 'Only for overrides']) ?>
                        <?= form_textarea($p, 'note', 'Note', $input['note'] ?? '', $errors, ['last' => true]) ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col-xxl-8 col-xl-7">
                <div class="card" id="batch-release-form-readings-card">
                    <div class="card-header"><h5 class="card-title">Readings since the stage began</h5></div>
                    <div class="card-body p-0"><div class="table-responsive">
                        <table class="table table-hover mb-0" id="batch-release-form-readings-table">
                            <thead class="thead-light"><tr><th>Taken</th><th>Measurement</th><th>Value</th><th>Spec</th></tr></thead>
                            <tbody>
                            <?php foreach ($readings as $reading): $rid = (int) $reading['id']; $color = ['pass' => 'success', 'fail' => 'danger'][$reading['spec_result']] ?? 'secondary'; ?>
                                <tr id="batch-release-form-reading-<?= e($rid) ?>">
                                    <td id="batch-release-form-reading-<?= e($rid) ?>-taken"><?= e(format_datetime($reading['taken_at'])) ?></td>
                                    <td id="batch-release-form-reading-<?= e($rid) ?>-measurement"><?= e($reading['measurement_name']) ?></td>
                                    <td id="batch-release-form-reading-<?= e($rid) ?>-value"><?= e($fmt($reading['value'], $reading['decimals'])) ?> <small class="text-muted"><?= e($reading['unit']) ?></small></td>
                                    <td id="batch-release-form-reading-<?= e($rid) ?>-spec"><?= badge(humanize($reading['spec_result']), $color) ?> <small class="text-muted"><?= e($range($reading['min_value'], $reading['max_value'], $reading['decimals'])) ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($readings === []): ?><tr><td colspan="4" class="text-center text-muted py-4">No readings since the stage began.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div></div>
                </div>
            </div>
            <div class="col-xxl-4 col-xl-5">
                <div class="card" id="batch-release-form-targets-card">
                    <div class="card-header"><h5 class="card-title">QC targets for <?= e($batch['stage_name']) ?></h5></div>
                    <div class="card-body">
                        <?php foreach ($qc_targets as $target): $tid = str_replace('_', '-', $target['measurement_type_code']); ?>
                            <?= detail_row('batch-release-form-target-' . $tid, $target['measurement_name'], e($range($target['min_value'], $target['max_value'], $target['decimals'])) . ' <small class="text-muted">' . e($target['unit']) . ($target['target_value'] !== null ? ', target ' . e($fmt($target['target_value'], $target['decimals'])) : '') . '</small>') ?>
                        <?php endforeach; ?>
                        <?php if ($qc_targets === []): ?><p class="text-muted fs-12 mb-0">No specs defined for this product and stage.</p><?php endif; ?>
                        <?php if ($stage_instructions): ?><p class="fs-12 mt-3 mb-0" id="batch-release-form-instructions"><span class="text-muted">Recipe notes:</span> <?= e($stage_instructions) ?></p><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="row mt-4">
            <div class="col-lg-12">
                <div class="card" id="batch-release-form-sensory-card">
                    <div class="card-header"><h5 class="card-title">Sensory panels</h5></div>
                    <div class="card-body p-0"><div class="table-responsive">
                        <table class="table table-hover mb-0" id="batch-release-form-sensory-table">
                            <thead class="thead-light"><tr><th>Panel date</th><th>Panelist</th><th>Sample</th><th>Verdict</th><th>Faults</th></tr></thead>
                            <tbody>
                            <?php foreach ($sensory as $record): $sid = (int) $record['id']; $faults = json_decode((string) $record['faults'], true) ?: [];
                                $faultText = implode(', ', array_map(static fn($f) => humanize($f['fault']) . ' ' . (int) $f['intensity'], $faults)); ?>
                                <tr id="batch-release-form-sensory-<?= e($sid) ?>">
                                    <td id="batch-release-form-sensory-<?= e($sid) ?>-panel-on"><?= e(format_date($record['panel_on'])) ?></td>
                                    <td id="batch-release-form-sensory-<?= e($sid) ?>-panelist"><?= e($record['panelist'] ?? '') ?></td>
                                    <td id="batch-release-form-sensory-<?= e($sid) ?>-sample"><?= e($record['sample_code'] ?? '') ?></td>
                                    <td id="batch-release-form-sensory-<?= e($sid) ?>-verdict"><?= badge(humanize($record['verdict']), ['pass' => 'success', 'hold' => 'warning', 'fail' => 'danger'][$record['verdict']] ?? 'secondary') ?></td>
                                    <td id="batch-release-form-sensory-<?= e($sid) ?>-faults"><?= e($faultText) ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($sensory === []): ?><tr><td colspan="5" class="text-center text-muted py-4">No sensory panels since the stage began.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div></div>
                </div>
            </div>
        </div>
    </form>
</div>
