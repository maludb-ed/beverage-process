<?php /** @var array $receipt  @var array $lines  @var array $errors  @var array $lineErrors  @var array $catalog  @var array $suppliers  @var array $premises  @var array $locations  @var array $openOrders */
$id = $receipt['id'] ?? null;
$isEdit = $id !== null;
$title = $isEdit ? 'Edit ' . ($receipt['number'] ?? 'Receipt') : 'Add Receipt';
$cancelUrl = $isEdit ? '/receipts/' . $id : '/receipts/';
$p = 'receipt-form';
$receivedAt = $receipt['received_at'] ?? (new DateTimeImmutable('now', new DateTimeZone((string) config('app.timezone'))))->format('Y-m-d\TH:i');
$receivedAt = substr(str_replace(' ', 'T', (string) $receivedAt), 0, 16);
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'receipt-form', 'crumbs' => ['Receiving' => null, 'Receipts' => '/receipts/', $isEdit ? 'Edit' : 'Add' => null], 'actionsHtml' => form_actions('receipt-form', $cancelUrl, 'Save Receipt')]) ?>
<div class="main-content" id="receipt-form-content">
    <form id="receipt-form" method="post" action="/receipts/save" hx-post="/receipts/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <div class="row"><div class="col-lg-12">
            <div class="card" id="receipt-form-header-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Delivery</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Save as a draft, then post to create lots and stock.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'receipt-form-errors']) ?>
                    <?= form_select($p, 'purchase_order_id', 'Purchase order', $openOrders, $receipt['purchase_order_id'] ?? '', $errors, [
                        'blank' => 'Unplanned (no order)', 'help' => 'Choosing an order loads its outstanding lines.',
                        'extra' => ' hx-get="/receipts/po-lines" hx-trigger="change" hx-target="#receipt-form-lines" hx-swap="innerHTML"',
                    ]) ?>
                    <?= form_select($p, 'supplier_id', 'Supplier', $suppliers, $receipt['supplier_id'] ?? '', $errors, ['required' => true, 'blank' => 'Choose a supplier', 'help' => 'Taken from the order when one is chosen.']) ?>
                    <?= form_select($p, 'premises_id', 'Premises', $premises, $receipt['premises_id'] ?? '', $errors, ['required' => true, 'blank' => count($premises) === 1 ? null : 'Choose a premises']) ?>
                    <?= form_select($p, 'receiving_location_id', 'Received into', $locations, $receipt['receiving_location_id'] ?? (count($locations) === 1 ? array_key_first($locations) : ''), $errors, ['required' => true, 'blank' => 'Choose a location']) ?>
                    <?= form_input($p, 'received_at', 'Received at', $receivedAt, $errors, ['type' => 'datetime-local', 'icon' => 'feather-clock', 'required' => true]) ?>
                    <?= form_input($p, 'delivery_note_ref', 'Delivery note', $receipt['delivery_note_ref'] ?? '', $errors, ['maxlength' => 80, 'icon' => 'feather-file-text']) ?>
                    <?= form_textarea($p, 'notes', 'Notes', $receipt['notes'] ?? '', $errors, ['last' => true]) ?>
                </div>
            </div>
            <div class="card" id="receipt-form-lines-card">
                <div class="card-header">
                    <h5 class="card-title">Lines</h5>
                    <button type="button" class="btn btn-sm btn-light-brand" id="receipt-form-add-line-btn"
                            hx-get="/receipts/line-row" hx-target="#receipt-form-lines" hx-swap="beforeend"
                            hx-include="#receipt-form-field-supplier-id" hx-vals='js:{n: "n" + Date.now()}'><i class="feather-plus me-1"></i>Add line</button>
                </div>
                <div class="card-body" id="receipt-form-lines">
                    <?php foreach ($lines as $n => $line): ?>
                        <?= view('receipts/partials/form-line.php', ['n' => $n, 'line' => $line, 'catalog' => $catalog, 'lineErrors' => $lineErrors[$n] ?? []]) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
