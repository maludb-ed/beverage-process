<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/production-orders/queries.php';

// Pattern A fragment: one vessel-plan row. Called to add a row and on vessel change
// (to refresh the capacity warning against the planned volume in the form).
$user = require_role('production');
$pdo = db();
$n = preg_replace('/[^a-z0-9]/i', '', request_string('n', 20)) ?: 'n' . time();
$raw = $_GET['vessels'][$n] ?? [];
$raw = is_array($raw) ? $raw : [];
$row = [
    'vessel_id' => (int) ($raw['vessel_id'] ?? 0) ?: null, 'role' => (string) ($raw['role'] ?? 'primary'),
    'planned_from' => (string) ($raw['planned_from'] ?? ''), 'planned_to' => (string) ($raw['planned_to'] ?? ''),
];
$gal = str_replace(',', '', request_string('planned_volume_gal', 12));
echo view('production-orders/partials/vessel-row.php', [
    'n' => $n, 'row' => $row, 'rowErrors' => [], 'vesselCatalog' => production_vessel_catalog($pdo),
    'volumeL' => is_numeric($gal) ? from_display((float) $gal, 'L') : null,
]);
