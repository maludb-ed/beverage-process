<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/receipts/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/receipts/validation.php';

// The posting transaction every later posting handler copies: lock the document,
// validate, write lots + ledger rows + side effects through query functions,
// log, commit, then navigate with HX-Trigger events for listening regions.
require_post();
verify_csrf();
$user = require_role('receiving');
$pdo = db();
$id = request_integer('id') ?? not_found('That receipt does not exist.');

try {
    $pdo->beginTransaction();
    $receipt = find_receipt($pdo, $id, true) ?? not_found('That receipt does not exist.');
    if ($receipt['status'] !== 'draft') {
        throw new RuntimeException('Receipt ' . $receipt['number'] . ' is already ' . $receipt['status'] . '.');
    }
    $lines = find_receipt_lines($pdo, $id);
    if (array_filter($lines, static fn($l) => (float) $l['qty_base'] > 0) === []) {
        throw new RuntimeException('Receipt ' . $receipt['number'] . ' has no quantities to post.');
    }
    foreach ($lines as $line) {
        if (receipt_needs_weigh_tag($line) && $line['weigh_tag_id'] === null) {
            throw new RuntimeException('Line ' . $line['line_no'] . ' (' . $line['item_code'] . ') needs a weigh tag before posting.');
        }
    }
    $lots = post_receipt($pdo, $receipt, $lines, (int) $user['id']);
    log_activity($pdo, 'receipt_posted', 'goods_receipt', $id, $receipt['number'], ['status' => 'draft'], ['status' => 'posted', 'lots' => $lots],
        ['purchase_order' => $receipt['po_number']], 'receipt-view');
    $pdo->commit();
    $quarantined = count(array_filter($lots, static fn($l) => $l['quality_status'] !== 'released'));
    flash('success', 'Receipt ' . $receipt['number'] . ' posted: ' . count($lots) . ' lot(s) created' . ($quarantined ? ', ' . $quarantined . ' in quarantine until released.' : '.'));
    hx_trigger('receiptsChanged, lotsChanged, inventoryChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('receipt post failed: ' . $exception->getMessage());
    flash('error', !$exception instanceof PDOException && $exception instanceof RuntimeException ? $exception->getMessage() : (db_error_message($exception) ?? 'The receipt could not be posted.'));
}
hx_location('/receipts/' . $id);
