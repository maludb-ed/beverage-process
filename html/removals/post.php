<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/removals/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/fulfillment.php';

// Posting a removal changes tax state: ledger rows (bonded out, taproom in, or return in), keg movements,
// tax determination in wine gallons. One transaction; copies html/receipts/post.php.
require_post();
verify_csrf();
$user = require_role('compliance');
$pdo = db();
$id = request_integer('id') ?? not_found('That removal does not exist.');

try {
    $pdo->beginTransaction();
    $before = find_removal($pdo, $id, true) ?? not_found('That removal does not exist.');
    $result = post_removal($pdo, $id, (int) $user['id']);
    $posted = $result['removal'];
    log_activity($pdo, 'removal_posted', 'removal', $id, $before['number'], ['status' => 'draft'], [
        'destination_kind' => $before['destination_kind'], 'customer' => $before['customer_name'], 'units' => $result['units'],
        'wine_gallons' => (float) $posted['wine_gallons'], 'tax_amount' => $posted['tax_amount'] === null ? null : (float) $posted['tax_amount'],
    ], ['direction' => $before['direction'], 'tax_class' => $posted['tax_class']], 'removal-view');
    foreach ($result['kegs'] as $keg) {
        if ($keg['event'] !== null) {
            log_activity($pdo, $keg['event'], 'keg', $keg['keg_id'], $keg['serial'], null, null, ['removal' => $before['number']], 'removal-view');
        }
    }
    orders_refresh_statuses($pdo, orders_for_removal($pdo, $id), 'removal-view');
    $pdo->commit();
    $gal = number_format((float) $posted['wine_gallons'], 2);
    flash('success', $before['number'] . ' posted: ' . $result['units'] . ' units, ' . $gal . ' wine gallons'
        . ($posted['tax_determined'] ? ', tax determined $' . number_format((float) $posted['tax_amount'], 2) . '.' : '.'));
    hx_trigger('removalsChanged, inventoryChanged, kegsChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('removal post failed: ' . $exception->getMessage());
    flash('error', !$exception instanceof PDOException && $exception instanceof RuntimeException ? $exception->getMessage() : (db_error_message($exception) ?? 'The removal could not be posted.'));
}
hx_location('/removals/' . $id);
