<?php /** @var array $standing  @var array $lines  @var array $errors  @var array $lineErrors  @var array $catalog  @var array $customers  @var array $premises */
$id = $standing['id'] ?? null;
$isEdit = $id !== null;
$cancelUrl = $isEdit ? '/orders/standing/' . $id : '/orders/standing';
$p = 'standing-order-form';
$days = array_combine(range(1, 28), array_map('standing_ordinal', range(1, 28)));
?>
<?= view('shared/page-header.php', ['title' => $isEdit ? 'Edit ' . $standing['number'] : 'New Standing Order', 'screen' => 'standing-order-form',
    'crumbs' => ['Sales' => null, 'Standing orders' => '/orders/standing', $isEdit ? 'Edit' : 'New' => null], 'actionsHtml' => form_actions('standing-order-form', $cancelUrl, 'Save Standing Order')]) ?>
<div class="main-content" id="standing-order-form-content">
    <form id="standing-order-form" method="post" action="/orders/standing/save" hx-post="/orders/standing/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= e($id) ?>" /><?php endif; ?>
        <div class="row"><div class="col-lg-12">
            <div class="card" id="standing-order-form-header-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Standing order</span><span class="fs-12 fw-normal text-muted">Recurring deliveries. Each date counts as standing demand in planning until you turn it into an order.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'standing-order-form-errors']) ?>
                    <?= form_select($p, 'customer_id', 'Customer', array_map(static fn($c) => $c['name'], $customers), $standing['customer_id'] ?? '', $errors, ['required' => true, 'blank' => 'Choose a customer']) ?>
                    <?= form_select($p, 'premises_id', 'Premises', $premises, $standing['premises_id'] ?? '', $errors, ['required' => true, 'blank' => count($premises) === 1 ? null : 'Choose a premises']) ?>
                    <?= form_select($p, 'frequency', 'How often', STANDING_FREQUENCIES, $standing['frequency'] ?? 'weekly', $errors, ['required' => true]) ?>
                    <?= form_input($p, 'interval_weeks', 'Every how many weeks', $standing['interval_weeks'] ?? 2, $errors, ['type' => 'number', 'min' => 2, 'max' => 52, 'step' => 1, 'icon' => 'feather-repeat', 'help' => 'Used with "Every few weeks".']) ?>
                    <?= form_select($p, 'weekday', 'Day of the week', STANDING_WEEKDAYS, $standing['weekday'] ?? 5, $errors, ['help' => 'Used with weekly schedules.']) ?>
                    <?= form_select($p, 'day_of_month', 'Day of the month', $days, $standing['day_of_month'] ?? 1, $errors, ['help' => 'Used with "Every month"; 1st to 28th so every month has it.']) ?>
                    <?= form_input($p, 'starts_on', 'Starts on', $standing['starts_on'] ?? today(), $errors, ['type' => 'date', 'icon' => 'feather-calendar', 'required' => true, 'help' => 'Every few weeks counts from the week of this date.']) ?>
                    <?= form_input($p, 'ends_on', 'Ends on', $standing['ends_on'] ?? '', $errors, ['type' => 'date', 'icon' => 'feather-calendar', 'help' => 'Leave blank to keep it running.']) ?>
                    <?= form_textarea($p, 'notes', 'Notes', $standing['notes'] ?? '', $errors, ['last' => true]) ?>
                </div>
            </div>
            <div class="card" id="standing-order-form-lines-card">
                <div class="card-header">
                    <h5 class="card-title">Each delivery</h5>
                    <button type="button" class="btn btn-sm btn-light-brand" id="standing-order-form-add-line-btn"
                            hx-get="/orders/line-row" hx-target="#standing-order-form-lines" hx-swap="beforeend" hx-vals='js:{n: "n" + Date.now()}'><i class="feather-plus me-1"></i>Add line</button>
                </div>
                <div class="card-body" id="standing-order-form-lines">
                    <?php foreach ($lines as $n => $line): ?>
                        <?= view('orders/partials/form-line.php', ['n' => $n, 'line' => $line, 'catalog' => $catalog, 'lineErrors' => $lineErrors[$n] ?? [], 'canPrice' => true, 'availability' => null, 'orderId' => null]) ?>
                    <?php endforeach; ?>
                    <div class="fs-12 text-muted">A blank price uses the format's list price when the order is made.</div>
                </div>
            </div>
        </div></div>
    </form>
</div>
