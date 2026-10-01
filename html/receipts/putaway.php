<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/receipts/queries.php';

$user = require_role('receiving');
$pdo = db();
$id = request_integer('id') ?? not_found('That receipt does not exist.');
$receipt = find_receipt($pdo, $id) ?? not_found('That receipt does not exist.');
if ($receipt['status'] !== 'posted') {
    flash('warning', 'Post the receipt before putting stock away.');
    hx_location('/receipts/' . $id);
}
$rows = putaway_rows($pdo, $receipt);
log_screen_entered('putaway', 'goods_receipt', $id, $receipt['number']);
render_screen('Putaway ' . $receipt['number'], 'putaway', view('receipts/partials/putaway-form.php', [
    'receipt' => $receipt, 'rows' => $rows, 'destinations' => putaway_location_options($pdo, (int) $receipt['premises_id'], $receipt['receiving_tax_state']), 'errors' => [],
]), 'goods_receipt', $id);
