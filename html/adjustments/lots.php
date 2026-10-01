<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/adjustments/queries.php';

// Pattern A fragment: the lot select for a chosen item (lots with stock here first, any quality status).
$user = require_role('receiving');
$n = preg_replace('/[^a-z0-9]/i', '', request_string('n', 20)) ?: 'n1';
$itemId = request_integer('item_id') ?? (int) ($_GET['lines'][$n]['item_id'] ?? 0);
$locationId = request_integer('location_id');
$lines = adjustment_prepare_lines(db(), $locationId, [$n => ['item_id' => $itemId]]);
echo view('adjustments/partials/lot-select.php', [
    'p' => 'adjustment-form-line-' . $n, 'base' => 'lines[' . $n . ']', 'lots' => $lines[$n]['lots'], 'selected' => '', 'item' => $lines[$n]['item_facts'], 'error' => '',
]);
