<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/standing.php';

// Pause or resume a standing order (active = 0 or 1).
require_post();
verify_csrf();
$user = require_role('sales');
$pdo = db();
$id = request_integer('sub_id') ?? not_found('That standing order does not exist.');
$standing = find_standing_order($pdo, $id) ?? not_found('That standing order does not exist.');
$active = post_bool('active');
try {
    $pdo->beginTransaction();
    $after = set_standing_order_active($pdo, $id, $active);
    log_activity($pdo, $active ? 'standing_order_updated' : 'standing_order_deactivated', 'standing_order', $id, $standing['number'],
        ['active' => (bool) $standing['active']], ['active' => $active], [], 'standing-order-view');
    $pdo->commit();
    flash('success', $standing['number'] . ($active ? ' resumed.' : ' paused; it no longer counts as demand.'));
    hx_trigger('ordersChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($exception->getMessage());
    flash('error', 'The standing order could not be updated.');
}
hx_location('/orders/standing/' . $id);
