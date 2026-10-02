<?php /** @var array $input  @var array $errors  @var array $lots */
$p = 'pomace-disposition-form';
$lot = $lots[(int) ($input['lot_id'] ?? 0)] ?? null;
?>
<?= view('shared/page-header.php', ['title' => 'Pomace disposition', 'screen' => 'pomace-disposition-form', 'crumbs' => ['Production' => null, 'Pomace disposition' => null], 'actionsHtml' => form_actions('pomace-disposition-form', $lot !== null ? '/lots/' . (int) $lot['lot_id'] : '/press-runs/', 'Save Disposition')]) ?>
<div class="main-content" id="pomace-disposition-form-content">
    <form id="pomace-disposition-form" method="post" action="/dispositions/save" hx-post="/dispositions/save" hx-target="#page-content" hx-swap="innerHTML">
        <?= csrf_field() ?>
        <div class="row"><div class="col-lg-12">
            <div class="card" id="pomace-disposition-form-card">
                <div class="card-body">
                    <div class="mb-4"><h5 class="fw-bold mb-0 me-4"><span class="d-block mb-2">Where the pomace went</span><span class="fs-12 fw-normal text-muted">Compost and waste are recorded as destroyed; farm, sale and other as removed.</span></h5></div>
                    <?= view('shared/validation-errors.php', ['errors' => array_values($errors), 'id' => 'pomace-disposition-form-errors']) ?>
                    <?= form_select($p, 'lot', 'Lot', array_map(static fn($l) => $l['label'], $lots), $input['lot_id'] ?? '', $errors, ['name' => 'lot_id', 'required' => true, 'blank' => $lots === [] ? 'No co-product lots with stock' : 'Choose a lot']) ?>
                    <?= form_input($p, 'qty_lb', 'Weight', $input['qty_lb'] ?? '', $errors, ['type' => 'number', 'step' => 'any', 'min' => '0', 'required' => true, 'icon' => 'feather-hash', 'suffix' => display_unit('kg')]) ?>
                    <?= form_select($p, 'destination', 'Destination', DISPOSITION_DESTINATIONS, $input['destination'] ?? '', $errors, ['required' => true, 'blank' => 'Choose a destination']) ?>
                    <?= form_input($p, 'recipient', 'Recipient', $input['recipient'] ?? '', $errors, ['maxlength' => 120, 'icon' => 'feather-user', 'placeholder' => 'Farm, buyer or hauler']) ?>
                    <?= form_input($p, 'disposed_at', 'Disposed at', $input['disposed_at'] ?? '', $errors, ['type' => 'datetime-local', 'icon' => 'feather-clock', 'required' => true]) ?>
                    <?= form_textarea($p, 'note', 'Note', $input['note'] ?? '', $errors, ['last' => true]) ?>
                </div>
            </div>
        </div></div>
    </form>
</div>
