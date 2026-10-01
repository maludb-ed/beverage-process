<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/adjustments/queries.php';

// Pattern A fragment: one empty adjustment line.
$user = require_role('receiving');
$pdo = db();
$n = preg_replace('/[^a-z0-9]/i', '', request_string('n', 20)) ?: 'n' . time();
$lines = adjustment_prepare_lines($pdo, request_integer('location_id'), [$n => []]);
echo view('adjustments/partials/line-row.php', ['n' => $n, 'line' => $lines[$n], 'itemOptions' => item_options($pdo), 'lineErrors' => []]);
