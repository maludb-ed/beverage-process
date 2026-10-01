<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/dispositions/queries.php';

$user = require_role('production');
$pdo = db();
$lots = find_co_product_lot_options($pdo);
// Prefill (manifest): lot_number, destination.
$lotNumber = request_string('lot_number', 40);
$lotId = $lotNumber !== '' ? array_search($lotNumber, array_map(static fn($l) => $l['lot_number'], $lots), true) : false;
$destination = request_string('destination', 20);
$input = ['lot_id' => $lotId !== false ? $lotId : (count($lots) === 1 ? array_key_first($lots) : ''), 'destination' => in_options($destination, DISPOSITION_DESTINATIONS) ? $destination : '', 'disposed_at' => batches_datetime_local()];
$lot = $lots[(int) $input['lot_id']] ?? null;
if ($lot !== null) {
    $input['qty_lb'] = (string) round((float) to_display($lot['on_hand'], $lot['base_unit_code']), 1);
}
log_screen_entered('pomace-disposition-add', 'lot', $lot !== null ? (int) $lot['lot_id'] : null, $lot['lot_number'] ?? null);
render_screen('Pomace disposition', 'pomace-disposition-add', view('dispositions/partials/form.php', ['input' => $input, 'errors' => [], 'lots' => $lots]), 'lot', $lot !== null ? (int) $lot['lot_id'] : null);
