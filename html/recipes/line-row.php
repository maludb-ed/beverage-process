<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/recipes/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/recipes/validation.php';

// Pattern A fragment: one line row. Added with the Add line button; re-rendered when the item or
// basis changes so the unit label follows and the consumption mode defaults from the item.
$user = require_role('production');
$pdo = db();
$n = preg_replace('/[^a-z0-9]/i', '', request_string('n', 20)) ?: 'n' . time();
$raw = $_GET['lines'][$n] ?? [];
$raw = is_array($raw) ? $raw : [];
$catalog = recipe_item_catalog($pdo);
$itemId = (int) ($raw['item_id'] ?? 0);
$mode = (string) ($raw['mode'] ?? '');
if ($itemId && isset($catalog[$itemId]) && (request_string('item_changed', 1) === '1' || $mode === '')) {
    $mode = $catalog[$itemId]['consumption_mode'];
}
$stageCodes = [];
foreach (is_array($_GET['stages'] ?? null) ? $_GET['stages'] : [] as $stageRow) {
    if (is_array($stageRow) && ($stageRow['stage_code'] ?? '') !== '') {
        $stageCodes[(string) $stageRow['stage_code']] = true;
    }
}
$line = [
    'seq' => (string) ($raw['seq'] ?? request_string('seq', 6)), 'item_id' => $itemId ?: null, 'stage_code' => (string) ($raw['stage_code'] ?? ''),
    'purpose' => (string) ($raw['purpose'] ?? 'other'), 'basis' => isset(RECIPE_BASES[$raw['basis'] ?? '']) ? $raw['basis'] : 'per_volume',
    'qty' => (string) ($raw['qty'] ?? ''), 'consumption_mode' => $mode ?: 'explicit', 'notes' => (string) ($raw['notes'] ?? ''),
];
echo view('recipes/partials/line-row.php', ['n' => $n, 'line' => $line, 'catalog' => $catalog, 'stageCodes' => array_keys($stageCodes), 'stageNames' => recipe_stage_names($pdo), 'lineErrors' => []]);
