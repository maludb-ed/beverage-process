<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/items/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/inventory/queries.php';

$user = require_login();
$id = request_integer('id') ?? not_found('That item does not exist.');
$pdo = db();
$item = find_item($pdo, $id) ?? not_found('That item does not exist.');
log_screen_entered('item-view', 'item', $id, $item['code']);
render_screen('Item ' . $item['code'], 'item-view', view('items/view.php', [
    'item' => $item, 'units' => find_item_units($pdo, $id), 'suppliers' => find_item_suppliers($pdo, $id),
    'stock' => find_item_stock($pdo, $id), 'balances' => find_item_balances($pdo, $id),
    'unitInput' => [], 'unitErrors' => [], 'canEdit' => user_can($user, 'receiving'),
]), 'item', $id);
