<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/production-orders/queries.php';

// Pattern A fragment: one equipment-plan row. Called to add a row and on resource change (to refresh the capacity
// warning against the planned volume in the form and the roles offered for the resource kind).
$user = require_role('production');
$pdo = db();
$n = preg_replace('/[^a-z0-9]/i', '', request_string('n', 20)) ?: 'n' . time();
$raw = $_GET['plan'][$n] ?? [];
$raw = is_array($raw) ? $raw : [];
$row = [
    'id' => (int) ($raw['id'] ?? 0) ?: null, 'resource' => (string) ($raw['resource'] ?? ''), 'role' => (string) ($raw['role'] ?? ''),
    'planned_from' => (string) ($raw['planned_from'] ?? ''), 'planned_to' => (string) ($raw['planned_to'] ?? ''),
    'all_day' => !array_key_exists('all_day', $raw) || (string) $raw['all_day'] === '1',
    'start_time' => (string) ($raw['start_time'] ?? ''), 'end_time' => (string) ($raw['end_time'] ?? ''), 'share' => false,
];
$gal = str_replace(',', '', request_string('planned_volume_gal', 12));
$catalog = reservation_resource_catalog($pdo);
echo view('production-orders/partials/plan-row.php', [
    'n' => $n, 'row' => $row, 'rowErrors' => [], 'groups' => reservation_resource_groups($catalog), 'catalog' => $catalog,
    'volumeL' => is_numeric($gal) ? from_display((float) $gal, 'L') : null, 'allowShare' => double_booking_allowed($pdo),
]);
