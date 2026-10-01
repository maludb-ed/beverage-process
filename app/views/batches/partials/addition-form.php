<?php /** @var array $batch  @var array $input  @var array $errors  @var array $items  @var ?array $item  @var array $lots  @var array $stages */
$p = 'batch-addition-form';
$body = form_select($p, 'item', 'Item', array_map(static fn($i) => $i['code'] . ' — ' . $i['name'], $items), $input['item_id'] ?? '', $errors, [
        'name' => 'item_id', 'required' => true, 'blank' => 'Choose an item',
        'extra' => ' hx-get="/batches/lot-options" hx-trigger="change" hx-target="#batch-addition-form-lot-slot" hx-swap="innerHTML"',
    ])
    . '<div id="batch-addition-form-lot-slot">' . view('batches/partials/addition-lot.php', ['item' => $item, 'lots' => $lots, 'input' => $input, 'errors' => $errors]) . '</div>'
    . form_input($p, 'qty', 'Quantity', $input['qty'] ?? '', $errors, ['type' => 'number', 'step' => 'any', 'min' => '0', 'required' => true, 'icon' => 'feather-hash'])
    . form_select($p, 'purpose', 'Purpose', BATCH_ADDITION_PURPOSES, $input['purpose'] ?? '', $errors, ['required' => true, 'blank' => 'Choose a purpose'])
    . form_select($p, 'stage', 'Stage', array_map(static fn($s) => $s['name'], $stages), $input['stage'] ?? '', $errors, ['required' => true])
    . form_input($p, 'added_at', 'Added at', $input['added_at'] ?? '', $errors, ['type' => 'datetime-local', 'icon' => 'feather-clock', 'required' => true])
    . form_textarea($p, 'note', 'Note', $input['note'] ?? '', $errors, ['last' => true]);
echo view('batches/partials/event-form.php', ['batch' => $batch, 'formId' => $p, 'action' => '/batches/' . (int) $batch['id'] . '/additions/save', 'title' => 'Addition',
    'saveLabel' => 'Save Addition', 'intro' => 'Consumes the lot; juice joins the batch volume.', 'bodyHtml' => $body, 'errors' => $errors]);
