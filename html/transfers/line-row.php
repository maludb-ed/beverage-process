<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/transfers/queries.php';

// Pattern A fragment: one empty transfer line, with items that have stock at the source location.
$user = require_role('receiving');
$pdo = db();
$n = preg_replace('/[^a-z0-9]/i', '', request_string('n', 20)) ?: 'n' . time();
$locationId = request_integer('from_location_id');
$lines = transfer_prepare_lines($pdo, $locationId, [$n => []]);
echo view('transfers/partials/line-row.php', [
    'n' => $n, 'line' => $lines[$n], 'itemOptions' => $locationId ? inventory_items_at_location($pdo, $locationId) : [], 'lineErrors' => [],
]);
