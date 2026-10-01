<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/dispositions/queries.php';

require_post();
verify_csrf();
$user = require_role('production');
$pdo = db();
$lots = find_co_product_lot_options($pdo);
$input = [
    'lot_id' => request_integer('lot_id'), 'qty_lb' => request_string('qty_lb', 20), 'destination' => request_string('destination', 20),
    'recipient' => request_string('recipient', 120), 'disposed_at' => request_string('disposed_at', 20), 'note' => request_string('note', 2000),
];
$errors = [];
$lot = $lots[$input['lot_id'] ?? 0] ?? null;
if ($lot === null) { $errors['lot'] = 'Choose a co-product lot with stock.'; }
$qty = post_decimal('qty_lb');
$kg = is_float($qty) && $lot !== null ? round((float) from_display($qty, $lot['base_unit_code']), 4) : null;
if ($qty === null || $qty === false || $qty <= 0) { $errors['qty_lb'] = 'Enter the weight that left.'; }
elseif ($lot !== null && $kg > (float) $lot['on_hand'] + 0.0005) { $errors['qty_lb'] = 'Only ' . fmt_qty($lot['on_hand'], $lot['base_unit_code']) . ' of this lot is on hand.'; }
if (!in_options($input['destination'], DISPOSITION_DESTINATIONS)) { $errors['destination'] = 'Choose a destination.'; }
$at = batches_datetime_field($input['disposed_at'], $errors, 'disposed_at', 'Enter when it left.');

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $saved = insert_disposition($pdo, (int) $lot['lot_id'], min($kg, (float) $lot['on_hand']), $input['destination'], $input['recipient'] ?: null, $at->format(DATE_ATOM), $input['note'] ?: null, (int) $user['id']);
        log_activity($pdo, 'pomace_disposed', 'lot', (int) $lot['lot_id'], $lot['lot_number'], ['qty_on_hand' => (float) $lot['on_hand']],
            ['disposition_id' => (int) $saved['id'], 'qty_base' => (float) $saved['qty_base'], 'destination' => $saved['destination'], 'recipient' => $saved['recipient'], 'ledger_group_id' => $saved['ledger_group_id']], [], 'pomace-disposition-add');
        $pdo->commit();
        flash('success', fmt_qty($saved['qty_base'], $lot['base_unit_code']) . ' of ' . $lot['lot_number'] . ' recorded as ' . DISPOSITION_DESTINATIONS[$saved['destination']] . '.');
        hx_trigger('lotsChanged, inventoryChanged');
        hx_location('/lots/' . (int) $lot['lot_id']);
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log('pomace disposition failed: ' . $exception->getMessage());
        $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The disposition could not be saved.') : $exception->getMessage();
    }
}
$input['disposed_at'] = $at?->format('Y-m-d\TH:i') ?? $input['disposed_at'];
http_response_code(422);
render_screen('Pomace disposition', 'pomace-disposition-add', view('dispositions/partials/form.php', ['input' => $input, 'errors' => $errors, 'lots' => $lots]), 'lot', $lot !== null ? (int) $lot['lot_id'] : null);
