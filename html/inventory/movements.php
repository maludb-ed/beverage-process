<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/inventory/queries.php';

$user = require_login();
$pdo = db();
$query = list_params('-occurred_at');
$locations = inventory_location_options(inventory_locations($pdo));

// Prefill: ?item=<code>, ?lot_number=<number>, ?location=<name> resolve by exact match to ids.
$resolved = inventory_resolve_prefill($pdo, request_string('item', 40), request_string('lot_number', 40), request_string('location', 80));
$itemId = request_integer('item_id') ?? $resolved['item_id'];
$lotId = request_integer('lot_id') ?? $resolved['lot_id'];
$locationId = request_integer('location_id') ?? $resolved['location_id'];
$locationId = $locationId !== null && isset($locations[$locationId]) ? $locationId : null;
$txnType = request_string('txn_type', 30);
$txnType = in_options($txnType, INVENTORY_TXN_TYPES) ? $txnType : '';
$validDate = static fn(string $v): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1 ? $v : '';
$dateFrom = $validDate(request_string('date_from', 10));
$dateTo = $validDate(request_string('date_to', 10));

$filters = ['item_id' => $itemId, 'lot_id' => $lotId, 'location_id' => $locationId, 'txn_type' => $txnType, 'date_from' => $dateFrom, 'date_to' => $dateTo];
$result = find_inventory_movements($pdo, $filters, $query['sort'], $query['page']);
$data = ['result' => $result, 'locations' => $locations, 'query' => ['sort' => $query['sort']] + $filters];

if (is_results_request('inventory-movements-results')) {
    header('Vary: HX-Request');
    echo view('inventory/partials/movements-table.php', $data);
    exit;
}
log_screen_entered('inventory-movements', $lotId ? 'lot' : ($itemId ? 'item' : ($locationId ? 'location' : null)), $lotId ?: ($itemId ?: $locationId));
render_screen('Movements', 'inventory-movements', view('inventory/movements-page.php', $data));
