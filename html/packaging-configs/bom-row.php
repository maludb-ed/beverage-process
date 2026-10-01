<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/packaging-configs/queries.php';

// Pattern A fragment: one BOM row. Called to add a row and on item change (refreshes the unit label).
$user = require_role('production');
$n = preg_replace('/[^a-z0-9]/i', '', request_string('n', 20)) ?: 'n' . time();
$raw = $_GET['bom'][$n] ?? [];
$raw = is_array($raw) ? $raw : [];
$line = ['item_id' => (int) ($raw['item_id'] ?? 0) ?: null, 'qty' => (string) ($raw['qty'] ?? '')];
echo view('packaging-configs/partials/bom-row.php', ['n' => $n, 'line' => $line, 'catalog' => packaging_bom_item_catalog(db()), 'lineErrors' => []]);
