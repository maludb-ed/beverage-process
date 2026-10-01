<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/receipts/queries.php';

require_post();
verify_csrf();
$user = require_role('receiving');
$pdo = db();
$id = request_integer('id') ?? not_found('That receipt does not exist.');
$receipt = find_receipt($pdo, $id) ?? not_found('That receipt does not exist.');
$destinations = putaway_location_options($pdo, (int) $receipt['premises_id'], $receipt['receiving_tax_state']);
$rows = putaway_rows($pdo, $receipt);
$byLine = [];
foreach ($rows as $n => $row) {
    $byLine[$row['line_id']] = $n;
}
$errors = [];
$moves = [];
foreach ((is_array($_POST['lines'] ?? null) ? $_POST['lines'] : []) as $raw) {
    $lineId = (int) ($raw['line_id'] ?? 0);
    $to = (int) ($raw['to_location_id'] ?? 0);
    if ($to === 0 || !isset($byLine[$lineId])) {
        continue;
    }
    if (!isset($destinations[$to]) || $to === (int) $receipt['receiving_location_id']) {
        $errors[] = 'Choose a destination with the same tax state for ' . $rows[$byLine[$lineId]]['lot_number'] . '.';
        continue;
    }
    $rows[$byLine[$lineId]]['to_location_id'] = $to;
    $moves[] = ['row' => $rows[$byLine[$lineId]], 'to' => $to];
}
if ($moves === [] && $errors === []) {
    $errors[] = 'Choose a destination for at least one lot.';
}
if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $now = (new DateTimeImmutable())->format(DATE_ATOM);
        $logged = [];
        foreach ($moves as $move) {
            $row = $move['row'];
            $group = new_group_id();
            $qty = lot_on_hand($pdo, (int) $row['lot_id'], (int) $receipt['receiving_location_id']);
            $common = [(int) $row['item_id'], (int) $row['lot_id']];
            insert_inventory_transaction($pdo, $group, 'transfer_out', $common[0], $common[1], (int) $receipt['receiving_location_id'], (int) $receipt['premises_id'],
                -$qty, (float) $row['unit_cost_base'], 'location', $move['to'], null, 'none', 'goods_receipt', $id,
                'putaway:' . $id . ':lot:' . $row['lot_id'] . ':out:' . $group, $now, (int) $user['id']);
            insert_inventory_transaction($pdo, $group, 'transfer_in', $common[0], $common[1], $move['to'], (int) $receipt['premises_id'],
                $qty, (float) $row['unit_cost_base'], 'location', (int) $receipt['receiving_location_id'], null, 'none', 'goods_receipt', $id,
                'putaway:' . $id . ':lot:' . $row['lot_id'] . ':in:' . $group, $now, (int) $user['id']);
            update_receipt_line_putaway($pdo, (int) $row['line_id'], $move['to']);
            $logged[] = ['lot' => $row['lot_number'], 'qty_base' => $qty, 'to' => $destinations[$move['to']]];
        }
        log_activity($pdo, 'putaway_recorded', 'goods_receipt', $id, $receipt['number'], null, ['moves' => $logged], [], 'putaway');
        $pdo->commit();
        flash('success', count($logged) . ' lot(s) put away.');
        hx_trigger('inventoryChanged, lotsChanged');
        hx_location('/receipts/' . $id);
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $errors[] = db_error_message($exception) ?? 'The stock could not be moved.';
    }
}
http_response_code(422);
render_screen('Putaway ' . $receipt['number'], 'putaway', view('receipts/partials/putaway-form.php', [
    'receipt' => $receipt, 'rows' => $rows, 'destinations' => $destinations, 'errors' => $errors,
]), 'goods_receipt', $id);
