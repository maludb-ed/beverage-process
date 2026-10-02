<?php /** @var array $order  @var array $lines  @var array $errors  @var array $lineErrors  @var array $catalog  @var array $suppliers  @var array $premises */
$id = $order['id'] ?? null;
$isEdit = $id !== null;
$title = $isEdit ? 'Edit ' . ($order['number'] ?? 'Purchase Order') : 'Add Purchase Order';
$cancelUrl = $isEdit ? '/purchase-orders/' . $id : '/purchase-orders/';
$p = 'purchase-order-form';
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'purchase-order-form', 'crumbs' => ['Purchasing' => null, 'Purchase Orders' => '/purchase-orders/', $isEdit ? 'Edit' : 'Add' => null], 'actionsHtml' => form_actions('purchase-order-form', $cancelUrl, 'Save Order')]) ?>
<div class="main-content" id="purchase-order-form-content">
    <form id="purchase-order-form" method="post" action="/purchase-orders/save" hx-post="/purchase-orders/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <div class="row"><div class="col-lg-12">
            <div class="card" id="purchase-order-form-header-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Order</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">Draft orders can change; approving opens the order for receiving.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'purchase-order-form-errors']) ?>
                    <?= view('purchase-orders/partials/supplier-block.php', ['suppliers' => $suppliers, 'selected' => $order['supplier_id'] ?? '', 'errors' => $errors]) ?>
                    <?= form_select($p, 'premises_id', 'Premises', $premises, $order['premises_id'] ?? '', $errors, ['required' => true, 'blank' => count($premises) === 1 ? null : 'Choose a premises']) ?>
                    <?= form_input($p, 'ordered_on', 'Ordered on', $order['ordered_on'] ?? today(), $errors, ['type' => 'date', 'icon' => 'feather-calendar']) ?>
                    <?= form_input($p, 'expected_on', 'Expected on', $order['expected_on'] ?? '', $errors, ['type' => 'date', 'icon' => 'feather-calendar']) ?>
                    <?= form_textarea($p, 'notes', 'Notes', $order['notes'] ?? '', $errors, ['last' => true]) ?>
                </div>
            </div>
            <div class="card" id="purchase-order-form-lines-card">
                <div class="card-header">
                    <h5 class="card-title">Lines</h5>
                    <button type="button" class="btn btn-sm btn-light-brand" id="purchase-order-form-add-line-btn"
                            hx-get="/purchase-orders/line-row" hx-target="#purchase-order-form-lines" hx-swap="beforeend"
                            hx-include="#purchase-order-form-field-supplier-id" hx-vals='js:{n: "n" + Date.now()}'><i class="feather-plus me-1"></i>Add line</button>
                </div>
                <div class="card-body" id="purchase-order-form-lines">
                    <?php foreach ($lines as $n => $line): ?>
                        <?= view('purchase-orders/partials/form-line.php', ['n' => $n, 'line' => $line, 'catalog' => $catalog, 'lineErrors' => $lineErrors[$n] ?? []]) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
