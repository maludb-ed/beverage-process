<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/orders/fulfillment.php';

require_post();
verify_csrf();
$user = require_role('sales', 'production');
$pdo = db();
$orderId = request_integer('order_id');
$formatId = request_integer('format');
$order = $orderId !== null ? (find_order($pdo, $orderId) ?? not_found('That order does not exist.')) : null;
$lineIds = array_values(array_filter(array_map('intval', is_array($_POST['line_ids'] ?? null) ? $_POST['line_ids'] : [])));
$configIds = array_values(array_filter(array_map('intval', array_keys(is_array($_POST['groups'] ?? null) ? $_POST['groups'] : []))));
$input = [];
foreach ($configIds as $configId) {
    $raw = $_POST['groups'][$configId] ?? [];
    [$batchId, $vesselId] = array_map('intval', explode(':', (string) ($raw['source'] ?? '')) + [0, 0]);
    $input[$configId] = ['units' => trim((string) ($raw['units'] ?? '')), 'batch_id' => $batchId, 'source_vessel_id' => $vesselId, 'source' => (string) ($raw['source'] ?? ''),
                         'output_location_id' => (int) ($raw['output_location_id'] ?? 0), 'run_on' => trim((string) ($raw['run_on'] ?? ''))];
}
$plan = orders_package_plan($pdo, $configIds, $orderId !== null ? $lineIds : null);
$errors = [];
if ($plan === []) {
    $errors['form'] = 'These order lines no longer need packaging.';
}
if ($errors === []) {
    try {
        $pdo->beginTransaction();
        [$runs, $errors] = orders_create_packaging_runs($pdo, $plan, $input, (int) $user['id']);
        if ($errors !== []) {
            throw new RuntimeException('Fix the highlighted formats.');
        }
        if ($runs === []) {
            throw new RuntimeException('Enter units for at least one format.');
        }
        $byOrder = [];
        foreach ($runs as $run) {
            log_activity($pdo, 'packaging_run_created', 'packaging_run', (int) $run['id'], $run['number'], null,
                ['batch' => $run['batch_number'], 'configuration' => $run['configuration_name'], 'units_out' => $run['units_out'], 'volume_in_l' => $run['volume_in_l'], 'order_lines' => $run['links']],
                ['from' => 'customer orders'], 'order-package');
            foreach ($run['order_ids'] as $oid) {
                $byOrder[$oid][] = $run['number'];
            }
        }
        foreach ($byOrder as $oid => $numbers) {
            $o = find_order($pdo, $oid);
            log_activity($pdo, 'order_packaging_runs_created', 'sales_order', $oid, $o['number'], null, ['packaging_runs' => $numbers], [], 'order-package');
        }
        orders_refresh_statuses($pdo, array_keys($byOrder), 'order-package');
        $pdo->commit();
        flash('success', 'Draft packaging ' . (count($runs) === 1 ? 'run ' : 'runs ') . implode(', ', array_column($runs, 'number'))
            . ' created. Enter ABV and CO2 (and lots for explicit materials) on each run, then post it.');
        hx_trigger('packagingRunsChanged, ordersChanged');
        hx_location(count($runs) === 1 ? '/packaging-runs/' . $runs[0]['id'] : ($orderId !== null ? '/orders/' . $orderId : '/packaging-runs/'));
    } catch (PDOException | RuntimeException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        if ($errors === []) {
            $errors['form'] = $exception instanceof PDOException ? (db_error_message($exception) ?? 'The packaging runs could not be created.') : $exception->getMessage();
        }
    }
}
http_response_code(422);
render_screen('Package for orders', 'order-package', view('orders/partials/package.php', [
    'order' => $order, 'formatId' => $formatId, 'plan' => $plan, 'input' => $input, 'errors' => $errors,
]), $order ? 'sales_order' : null, $orderId);
