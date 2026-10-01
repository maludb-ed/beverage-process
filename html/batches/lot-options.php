<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/batches/queries.php';

// Pattern A fragment: the lot select (released lots with stock, FEFO) and the unit select for the chosen addition item.
$user = require_role('production');
$pdo = db();
$items = batches_addition_item_catalog($pdo);
$item = $items[request_integer('item_id') ?? 0] ?? null;
$lots = $item !== null ? find_item_lot_options($pdo, (int) $item['id']) : [];
$input = ['lot_id' => $item !== null && $item['consumption_mode'] === 'backflush' && $lots !== [] ? array_key_first($lots) : '', 'unit' => $item['base_unit_code'] ?? ''];
echo view('batches/partials/addition-lot.php', ['item' => $item, 'lots' => $lots, 'input' => $input, 'errors' => []]);
