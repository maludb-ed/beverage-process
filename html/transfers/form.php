<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/transfers/queries.php';

$user = require_role('receiving');
$pdo = db();
$id = request_integer('id');
$locations = inventory_locations($pdo);
$lines = [];

if ($id !== null) {
    $transfer = find_transfer($pdo, $id) ?? not_found('That transfer does not exist.');
    if ($transfer['status'] !== 'draft') {
        hx_location('/transfers/' . $id);
    }
    foreach ($transfer['lines'] as $i => $line) {
        $lines['n' . ($i + 1)] = ['item_id' => (int) $line['item_id'], 'lot_id' => (int) $line['lot_id'],
            'qty' => round((float) to_display($line['qty_base'], $line['base_unit_code'], inventory_unit_kind($line['item_class'])), 3)];
    }
    $screen = 'transfer-edit';
} else {
    // Prefill: ?from_location=<name>&to_location=<name>&item=<code>&lot_number=<number>&qty=<display qty>.
    $from = inventory_resolve_prefill($pdo, '', '', request_string('from_location', 80))['location_id'];
    $to = inventory_resolve_prefill($pdo, '', '', request_string('to_location', 80))['location_id'];
    $resolved = inventory_resolve_prefill($pdo, request_string('item', 40), request_string('lot_number', 40), '');
    $transfer = ['from_location_id' => $from, 'to_location_id' => $to];
    if ($from !== null && $to !== null && ($locations[$from]['tax_state'] ?? null) !== ($locations[$to]['tax_state'] ?? null)) {
        $transfer['to_location_id'] = null;
    }
    $lotId = $resolved['lot_id'];
    if ($lotId !== null) {
        $lotItem = (int) $pdo->query('SELECT item_id FROM app.lots WHERE id = ' . (int) $lotId)->fetchColumn();
        $lines['n1'] = ['item_id' => $lotItem, 'lot_id' => $lotId, 'qty' => request_string('qty', 20)];
    } elseif ($resolved['item_id'] !== null) {
        $lines['n1'] = ['item_id' => $resolved['item_id'], 'qty' => request_string('qty', 20)];
    }
    if ($lines === []) {
        $lines = ['n1' => []];
    }
    $screen = 'transfer-add';
}
$fromId = isset($transfer['from_location_id']) ? (int) $transfer['from_location_id'] : null;
$lines = transfer_prepare_lines($pdo, $fromId, $lines);
$itemOptions = $fromId ? inventory_items_at_location($pdo, $fromId) : [];
log_screen_entered($screen, 'transfer', $id, $transfer['number'] ?? null);
render_screen($id ? 'Edit ' . $transfer['number'] : 'Add Transfer', $screen, view('transfers/partials/form.php', [
    'transfer' => $transfer, 'lines' => $lines, 'errors' => [], 'lineErrors' => [], 'locations' => $locations, 'itemOptions' => $itemOptions,
]), 'transfer', $id);
