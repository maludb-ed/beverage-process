<?php /** @var array $lot  @var array $attributes  @var int $certificateCount  @var array $input  @var array $errors  @var array $reasons */
$id = (int) $lot['id'];
$p = 'lot-release-form';
$targets = array_diff_key(['released' => 'Released', 'hold' => 'Hold', 'rejected' => 'Rejected', 'quarantine' => 'Quarantine'], [$lot['quality_status'] => true]);
$actions = '<a id="lot-release-form-cancel-btn" class="btn btn-light-brand" ' . nav_attrs('/lots/' . $id) . '><i class="feather-x me-2"></i><span>Cancel</span></a>'
    . '<button type="submit" form="lot-release-form" id="lot-release-form-save-btn" class="btn btn-primary" hx-confirm="Record this release decision for ' . e($lot['lot_number']) . '? Overrides are flagged in the audit trail."><i class="feather-check me-2"></i><span>Record decision</span></button>';
?>
<?= view('shared/page-header.php', ['title' => 'Release ' . $lot['lot_number'], 'screen' => 'lot-release-form', 'crumbs' => ['Receiving' => null, 'Lots' => '/lots/', $lot['lot_number'] => '/lots/' . $id, 'Release' => null], 'actionsHtml' => $actions]) ?>
<div class="main-content" id="lot-release-form-content">
    <form id="lot-release-form" method="post" action="/lots/<?= e($id) ?>/release" hx-post="/lots/<?= e($id) ?>/release" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <div class="row">
            <div class="col-xxl-4 col-xl-6">
                <div class="card" id="lot-release-form-evidence">
                    <div class="card-header"><h5 class="card-title">Evidence</h5></div>
                    <div class="card-body">
                        <?= detail_row('lot-release-form-current-status', 'Current status', status_badge($lot['quality_status'])) ?>
                        <?= detail_row('lot-release-form-certificates', 'Certificates', e($certificateCount > 0 ? $certificateCount . ' on file' : 'None')) ?>
                        <?php foreach ($attributes as $attribute): ?>
                            <?= detail_row('lot-release-form-attribute-' . str_replace('_', '-', $attribute['key']), LOT_ATTRIBUTE_KEYS[$attribute['key']] ?? $attribute['key'], e(($attribute['value_text'] ?? (float) $attribute['value_num']) . ' ' . ($attribute['unit_code'] ?? ''))) ?>
                        <?php endforeach; ?>
                        <?php if ($attributes === []): ?><p class="text-muted fs-12 mb-0">No attributes recorded.</p><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-xxl-8 col-xl-6">
                <div class="card" id="lot-release-form-card">
                    <div class="card-header"><h5 class="card-title">Decision</h5></div>
                    <div class="card-body">
                        <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'lot-release-form-errors']) ?>
                        <?= form_select($p, 'to_status', 'New status', $targets, $input['to_status'] ?? 'released', $errors, ['required' => true]) ?>
                        <?= form_select($p, 'basis', 'Basis', RELEASE_BASES, $input['basis'] ?? ($certificateCount > 0 ? 'coa' : 'inspection'), $errors, ['required' => true]) ?>
                        <?= form_checkbox($p, 'is_override', 'Override', !empty($input['is_override']), ['help' => 'Use when releasing against the evidence; a reason is required.']) ?>
                        <?= form_select($p, 'reason_code_id', 'Override reason', $reasons, $input['reason_code_id'] ?? '', $errors, ['blank' => 'Only for overrides']) ?>
                        <?= form_textarea($p, 'note', 'Note', $input['note'] ?? '', $errors, ['last' => true]) ?>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
