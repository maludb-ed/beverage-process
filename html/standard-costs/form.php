<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/standard-costs/queries.php';

$user = require_role();
$pdo = db();
$code = request_string('item', 40);
$cost = request_string('cost', 20);
$cost = is_numeric($cost) && (float) $cost >= 0 ? $cost : '';
$itemId = $code !== '' ? find_standard_cost_item_id_by_code($pdo, $code) : request_integer('item_id');
$input = ['item_id' => $itemId, 'cost' => $cost, 'effective_from' => today()];
log_screen_entered('standard-cost-add', 'item', $itemId, $code ?: null);
render_screen('Set Standard Cost', 'standard-cost-add', view('standard-costs/partials/form.php', [
    'input' => $input, 'errors' => [], 'catalog' => standard_cost_item_catalog($pdo), 'history' => $itemId !== null ? find_standard_cost_history($pdo, $itemId) : [],
]), 'item', $itemId);
