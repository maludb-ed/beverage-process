<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/suppliers/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/items/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/purchase-orders/queries.php';

$user = require_login();
$id = request_integer('id') ?? not_found('That supplier does not exist.');
$pdo = db();
$supplier = find_supplier($pdo, $id) ?? not_found('That supplier does not exist.');
$canEdit = user_can($user, 'receiving');
log_screen_entered('supplier-view', 'supplier', $id, $supplier['name']);
render_screen('Supplier ' . $supplier['name'], 'supplier-view', view('suppliers/view.php', [
    'supplier' => $supplier, 'items' => find_supplier_items($pdo, $id), 'itemInput' => [], 'itemErrors' => [], 'canEdit' => $canEdit,
    'itemOptions' => $canEdit ? item_options($pdo) : [], 'unitOptions' => $canEdit ? supplier_purchase_unit_options($pdo) : [],
    'orders' => find_purchase_orders($pdo, '', '-number', 1, null, $id)['rows'], 'performance' => find_supplier_performance($pdo, $id), 'user' => $user, 'history' => supplier_history($pdo, $id),
]), 'supplier', $id);
