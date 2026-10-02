<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/fulfillment.php';

// Delete the draft packaging runs one Package step created (the assistant's undo of order_package).
// Only draft runs are deleted; a run already posted stays and is reported.
require_post();
verify_csrf();
$user = require_role('sales', 'production');
$pdo = db();
$numbers = array_values(array_filter(array_map(static fn($n) => mb_substr(trim((string) $n), 0, 40), is_array($_POST['runs'] ?? null) ? $_POST['runs'] : [])));
$deleted = [];
$kept = [];
try {
    $pdo->beginTransaction();
    $orderIds = [];
    $find = $pdo->prepare('SELECT id, number, status FROM app.packaging_runs WHERE number = :n FOR UPDATE');
    foreach ($numbers as $number) {
        $find->execute(['n' => $number]);
        $run = $find->fetch();
        if ($run === false) {
            continue;
        }
        if ($run['status'] !== 'draft') {
            $kept[] = $run['number'] . ' (' . $run['status'] . ')';
            continue;
        }
        $orderIds = array_merge($orderIds, orders_for_packaging_run($pdo, (int) $run['id']));
        delete_packaging_run($pdo, (int) $run['id']);
        log_activity($pdo, 'packaging_run_deleted', 'packaging_run', (int) $run['id'], $run['number'], ['status' => 'draft'], null, ['from' => 'customer orders'], 'order-view');
        $deleted[] = $run['number'];
    }
    if ($deleted === []) {
        throw new RuntimeException($kept !== [] ? 'Nothing deleted: ' . implode(', ', $kept) . ' are no longer drafts.' : 'Those packaging runs no longer exist.');
    }
    orders_refresh_statuses($pdo, $orderIds, 'order-view');
    $pdo->commit();
    flash('success', 'Deleted draft packaging ' . (count($deleted) === 1 ? 'run ' : 'runs ') . implode(', ', $deleted) . ($kept !== [] ? '; kept ' . implode(', ', $kept) : '') . '.');
    hx_trigger('packagingRunsChanged, ordersChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($exception->getMessage());
    flash('error', !$exception instanceof PDOException ? $exception->getMessage() : 'The packaging runs could not be deleted.');
}
hx_location('/orders/');
