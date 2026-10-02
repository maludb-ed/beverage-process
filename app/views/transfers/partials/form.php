<?php /** @var array $transfer  @var array $lines  @var array $errors  @var array $lineErrors  @var array $locations  @var array $itemOptions */
$id = $transfer['id'] ?? null;
$isEdit = $id !== null;
$title = $isEdit ? 'Edit ' . ($transfer['number'] ?? 'Transfer') : 'Add Transfer';
$cancelUrl = $isEdit ? '/transfers/' . $id : '/transfers/';
$p = 'transfer-form';
$fromId = (int) ($transfer['from_location_id'] ?? 0);
$from = $locations[$fromId] ?? null;
$toOptions = $from !== null ? inventory_location_options($locations, $from['tax_state'], $fromId) : [];
$transferredAt = $transfer['transferred_at'] ?? (new DateTimeImmutable('now', new DateTimeZone((string) config('app.timezone'))))->format('Y-m-d\TH:i');
$transferredAt = preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', (string) $transferredAt) ? $transferredAt
    : (new DateTimeImmutable((string) $transferredAt))->setTimezone(new DateTimeZone((string) config('app.timezone')))->format('Y-m-d\TH:i');
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'transfer-form', 'crumbs' => ['Inventory' => null, 'Transfers' => '/transfers/', $isEdit ? 'Edit' : 'Add' => null], 'actionsHtml' => form_actions('transfer-form', $cancelUrl, 'Save Transfer')]) ?>
<div class="main-content" id="transfer-form-content">
    <form id="transfer-form" method="post" action="/transfers/save" hx-post="/transfers/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <div class="row"><div class="col-lg-12">
            <div class="card" id="transfer-form-header-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Move stock</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Save as a draft, then post to move the stock.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'transfer-form-errors']) ?>
                    <?= form_select($p, 'from_location', 'From', inventory_location_options($locations), $fromId ?: '', $errors, [
                        'name' => 'from_location_id', 'required' => true, 'blank' => 'Choose a source',
                        'extra' => ' hx-get="/transfers/destinations" hx-trigger="change" hx-target="#transfer-form-field-to-location-row" hx-swap="outerHTML" hx-include="#transfer-form-field-to-location"',
                    ]) ?>
                    <?= view('transfers/partials/to-select.php', ['toOptions' => $toOptions, 'selected' => $transfer['to_location_id'] ?? '', 'errors' => $errors, 'hasFrom' => $from !== null]) ?>
                    <?= form_input($p, 'transferred_at', 'Transferred at', $transferredAt, $errors, ['type' => 'datetime-local', 'icon' => 'feather-clock', 'required' => true]) ?>
                    <?= form_textarea($p, 'notes', 'Notes', $transfer['notes'] ?? '', $errors, ['last' => true]) ?>
                </div>
            </div>
            <div class="card" id="transfer-form-lines-card">
                <div class="card-header">
                    <h5 class="card-title">Lines</h5>
                    <button type="button" class="btn btn-sm btn-light-brand" id="transfer-form-add-line-btn"
                            hx-get="/transfers/line-row" hx-target="#transfer-form-lines" hx-swap="beforeend"
                            hx-include="#transfer-form-field-from-location" hx-vals='js:{n: "n" + Date.now()}'><i class="feather-plus me-1"></i>Add line</button>
                </div>
                <div class="card-body" id="transfer-form-lines">
                    <?php foreach ($lines as $n => $line): ?>
                        <?= view('transfers/partials/line-row.php', ['n' => $n, 'line' => $line, 'itemOptions' => $itemOptions, 'lineErrors' => $lineErrors[$n] ?? []]) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
