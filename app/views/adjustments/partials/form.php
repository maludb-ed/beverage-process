<?php /** @var array $adjustment  @var array $lines  @var array $errors  @var array $lineErrors  @var array $locations  @var array $reasons  @var array $itemOptions */
$id = $adjustment['id'] ?? null;
$isEdit = $id !== null;
$title = $isEdit ? 'Edit ' . ($adjustment['number'] ?? 'Adjustment') : 'Add Adjustment';
$cancelUrl = $isEdit ? '/adjustments/' . $id : '/adjustments/';
$p = 'adjustment-form';
$adjustedAt = $adjustment['adjusted_at'] ?? (new DateTimeImmutable('now', new DateTimeZone((string) config('app.timezone'))))->format('Y-m-d\TH:i');
$adjustedAt = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', (string) $adjustedAt) ? $adjustedAt
    : (new DateTimeImmutable((string) $adjustedAt))->setTimezone(new DateTimeZone((string) config('app.timezone')))->format('Y-m-d\TH:i');
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'adjustment-form', 'crumbs' => ['Inventory' => null, 'Adjustments' => '/adjustments/', $isEdit ? 'Edit' : 'Add' => null], 'actionsHtml' => form_actions('adjustment-form', $cancelUrl, 'Save Adjustment')]) ?>
<div class="main-content" id="adjustment-form-content">
    <form id="adjustment-form" method="post" action="/adjustments/save" hx-post="/adjustments/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <div class="row"><div class="col-lg-12">
            <div class="card" id="adjustment-form-header-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Correct stock</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Large adjustments need owner approval before they can be posted.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'adjustment-form-errors']) ?>
                    <?= form_select($p, 'location', 'Location', inventory_location_options($locations), $adjustment['location_id'] ?? '', $errors, ['name' => 'location_id', 'required' => true, 'blank' => 'Choose a location']) ?>
                    <?= form_select($p, 'reason', 'Reason', $reasons, $adjustment['reason_code_id'] ?? '', $errors, ['name' => 'reason_code_id', 'required' => true, 'blank' => 'Choose a reason']) ?>
                    <?= form_input($p, 'adjusted_at', 'Adjusted at', $adjustedAt, $errors, ['type' => 'datetime-local', 'icon' => 'feather-clock', 'required' => true]) ?>
                    <?= form_textarea($p, 'notes', 'Notes', $adjustment['notes'] ?? '', $errors, ['last' => true]) ?>
                </div>
            </div>
            <div class="card" id="adjustment-form-lines-card">
                <div class="card-header">
                    <h5 class="card-title">Lines</h5>
                    <button type="button" class="btn btn-sm btn-light-brand" id="adjustment-form-add-line-btn"
                            hx-get="/adjustments/line-row" hx-target="#adjustment-form-lines" hx-swap="beforeend"
                            hx-include="#adjustment-form-field-location" hx-vals='js:{n: "n" + Date.now()}'><i class="feather-plus me-1"></i>Add line</button>
                </div>
                <div class="card-body" id="adjustment-form-lines">
                    <?php foreach ($lines as $n => $line): ?>
                        <?= view('adjustments/partials/line-row.php', ['n' => $n, 'line' => $line, 'itemOptions' => $itemOptions, 'lineErrors' => $lineErrors[$n] ?? []]) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
