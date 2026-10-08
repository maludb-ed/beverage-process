<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/production-orders/queries.php';

$user = require_login();
$pdo = db();
$id = request_integer('id') ?? not_found('That production order does not exist.');
$order = find_production_order($pdo, $id) ?? not_found('That production order does not exist.');
log_screen_entered('production-order-view', 'production_order', $id, $order['number']);
$plan = find_order_plan($pdo, $id);
render_screen($order['number'], 'production-order-view', view('production-orders/partials/view.php', [
    'order' => $order, 'plan' => $plan, 'materials' => find_material_check($pdo, $id), 'allocations' => find_allocations($pdo, $id),
    'consumed' => find_order_consumed($pdo, $id), 'processing' => find_order_processing_time($pdo, $order, $plan), 'outputs' => find_order_outputs($pdo, $order), 'user' => $user,
]), 'production_order', $id);
