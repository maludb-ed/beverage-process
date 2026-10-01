<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/adjustments/queries.php';

$user = require_role('receiving');
$pdo = db();
$id = request_integer('id');
$lines = [];

if ($id !== null) {
    $adjustment = find_adjustment($pdo, $id) ?? not_found('That adjustment does not exist.');
    if (!in_array($adjustment['status'], ['draft', 'pending_approval'], true)) {
        hx_location('/adjustments/' . $id);
    }
    foreach ($adjustment['lines'] as $i => $line) {
        $lines['n' . ($i + 1)] = ['item_id' => (int) $line['item_id'], 'lot_id' => (int) $line['lot_id'], 'note' => $line['note'],
            'qty_delta' => round((float) to_display($line['qty_delta_base'], $line['base_unit_code'], inventory_unit_kind($line['item_class'])), 3),
            'unit_cost' => $line['unit_cost_base'] !== null ? (float) $line['unit_cost_base'] : ''];
    }
    $screen = 'adjustment-edit';
} else {
    // Prefill: ?location=<name>&item=<code>&lot_number=<number>&qty_delta=<display qty>&reason=<reason code>.
    $resolved = inventory_resolve_prefill($pdo, request_string('item', 40), request_string('lot_number', 40), request_string('location', 80));
    $reason = find_reason_code_by_code($pdo, strtoupper(request_string('reason', 20)));
    $adjustment = ['location_id' => $resolved['location_id'], 'reason_code_id' => $reason !== null && $reason['applies_to'] === 'adjustment' ? (int) $reason['id'] : null];
    $itemId = $resolved['item_id'];
    if ($resolved['lot_id'] !== null) {
        $itemId = (int) $pdo->query('SELECT item_id FROM app.lots WHERE id = ' . (int) $resolved['lot_id'])->fetchColumn();
    }
    $lines['n1'] = $itemId !== null ? ['item_id' => $itemId, 'lot_id' => $resolved['lot_id'], 'qty_delta' => request_string('qty_delta', 20)] : [];
    $screen = 'adjustment-add';
}
$lines = adjustment_prepare_lines($pdo, isset($adjustment['location_id']) ? (int) $adjustment['location_id'] : null, $lines);
log_screen_entered($screen, 'adjustment', $id, $adjustment['number'] ?? null);
render_screen($id ? 'Edit ' . $adjustment['number'] : 'Add Adjustment', $screen, view('adjustments/partials/form.php', [
    'adjustment' => $adjustment, 'lines' => $lines, 'errors' => [], 'lineErrors' => [], 'locations' => inventory_locations($pdo),
    'reasons' => inventory_reason_options($pdo, 'adjustment'), 'itemOptions' => item_options($pdo),
]), 'adjustment', $id);
