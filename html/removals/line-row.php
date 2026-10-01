<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/removals/queries.php';

// Pattern A fragment: one removal line. Added by "Add line"; re-rendered on lot change to show the
// available units and, for keg lots, the keg multi-select.
$user = require_role('compliance');
$pdo = db();
$n = preg_replace('/[^a-z0-9]/i', '', request_string('n', 20)) ?: 'n' . time();
$raw = is_array($_GET['lines'][$n] ?? null) ? $_GET['lines'][$n] : [];
$direction = request_string('direction', 3) === 'in' ? 'in' : 'out';
$options = removals_line_options($pdo, $direction, $direction === 'out' ? request_integer('from_location_id') : null, request_integer('customer_id'));
$line = [
    'lot_id' => (int) ($raw['lot_id'] ?? 0) ?: null, 'units' => (string) ($raw['units'] ?? ''),
    'keg_ids' => array_map('intval', is_array($raw['keg_ids'] ?? null) ? $raw['keg_ids'] : []),
];
echo view('removals/partials/line-row.php', ['n' => $n, 'line' => $line, 'options' => $options, 'direction' => $direction, 'lineErrors' => []]);
