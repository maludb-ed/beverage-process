<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/production-orders/queries.php';

$user = require_login();
$pdo = db();
$id = request_integer('id') ?? not_found('That production order does not exist.');
$order = find_production_order($pdo, $id) ?? not_found('That production order does not exist.');
log_screen_entered('production-order-view', 'production_order', $id, $order['number']);
render_screen($order['number'], 'production-order-view', view('production-orders/partials/view.php', [
    'order' => $order, 'vessels' => find_production_order_vessels($pdo, $id), 'conflicts' => find_vessel_conflicts($pdo, $id),
    'materials' => find_material_check($pdo, $id), 'allocations' => find_allocations($pdo, $id), 'user' => $user,
]), 'production_order', $id);
