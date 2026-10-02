<?php /** @var array $order  @var array $lines  @var array $errors  @var array $lineErrors  @var array $catalog  @var array $customers  @var array $premises  @var bool $canPrice */
$id = $order['id'] ?? null;
$isEdit = $id !== null;
$title = $isEdit ? 'Edit ' . ($order['number'] ?? 'Order') : 'New Order';
$cancelUrl = $isEdit ? '/orders/' . $id : '/orders/';
$p = 'order-form';
$customerOptions = array_map(static fn(array $c) => $c['name'], $customers);
$isDraft = ($order['status'] ?? 'draft') === 'draft';
?>
<?= view('shared/page-header.php', ['title' => $title, 'screen' => 'order-form', 'crumbs' => ['Sales' => null, 'Customer orders' => '/orders/', $isEdit ? 'Edit' : 'New' => null], 'actionsHtml' => form_actions('order-form', $cancelUrl, 'Save Order')]) ?>
<div class="main-content" id="order-form-content">
    <form id="order-form" method="post" action="/orders/save" hx-post="/orders/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <div class="row"><div class="col-lg-12">
            <div class="card" id="order-form-header-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Order</span><span class="fs-12 fw-normal text-muted text-truncate-1-line">A draft is not demand yet; confirming it counts it in packaging and planning.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'order-form-errors']) ?>
                    <?= form_select($p, 'customer_id', 'Customer', $customerOptions, $order['customer_id'] ?? '', $errors, ['required' => true, 'blank' => 'Choose a customer']) ?>
                    <?= form_select($p, 'destination_kind', 'Destination', ORDER_DESTINATIONS, $order['destination_kind'] ?? 'tax_paid_sale', $errors, ['required' => true, 'help' => 'Carried to the shipment. Starting an order from a customer page sets their usual destination.']) ?>
                    <?= form_select($p, 'premises_id', 'Premises', $premises, $order['premises_id'] ?? '', $errors, ['required' => true, 'blank' => count($premises) === 1 ? null : 'Choose a premises']) ?>
                    <?= form_input($p, 'ordered_on', 'Ordered on', $order['ordered_on'] ?? today(), $errors, ['type' => 'date', 'icon' => 'feather-calendar', 'required' => true]) ?>
                    <?= form_input($p, 'requested_on', 'Due on', $order['requested_on'] ?? '', $errors, ['type' => 'date', 'icon' => 'feather-clock', 'required' => true, 'help' => 'The date the customer wants it shipped.']) ?>
                    <?= form_input($p, 'customer_reference', 'Customer reference', $order['customer_reference'] ?? '', $errors, ['maxlength' => 60, 'icon' => 'feather-hash', 'help' => 'Their PO number; one order per reference.']) ?>
                    <?php if ($isDraft): ?>
                    <?= form_checkbox($p, 'fulfilled_outside', 'Already fulfilled outside the system', (bool) ($order['fulfilled_outside'] ?? false), ['help' => 'For past orders: saved as closed history, with no stock or tax effect.']) ?>
                    <?php endif; ?>
                    <?= form_textarea($p, 'notes', 'Notes', $order['notes'] ?? '', $errors, ['last' => true]) ?>
                </div>
            </div>
            <div class="card" id="order-form-lines-card">
                <div class="card-header">
                    <h5 class="card-title">Lines</h5>
                    <button type="button" class="btn btn-sm btn-light-brand" id="order-form-add-line-btn"
                            hx-get="/orders/line-row" hx-target="#order-form-lines" hx-swap="beforeend" hx-vals='js:{n: "n" + Date.now()}'><i class="feather-plus me-1"></i>Add line</button>
                </div>
                <div class="card-body" id="order-form-lines">
                    <?php foreach ($lines as $n => $line): ?>
                        <?= view('orders/partials/form-line.php', ['n' => $n, 'line' => $line, 'catalog' => $catalog, 'lineErrors' => $lineErrors[$n] ?? [], 'canPrice' => $canPrice, 'availability' => null, 'orderId' => $id]) ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
