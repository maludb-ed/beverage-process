<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/press-runs/queries.php';

// Pattern A fragment: one output row; re-rendered when its kind changes (juice → item, gal, brix, vessel; pomace → item, weight, location).
$user = require_role('production');
$pdo = db();
$n = preg_replace('/[^a-z0-9]/i', '', request_string('n', 20)) ?: 'n' . time();
$raw = is_array($_GET['outputs'][$n] ?? null) ? $_GET['outputs'][$n] : [];
$row = array_intersect_key($raw, array_flip(['kind', 'qty', 'brix', 'vessel_id', 'location_id']));
$catalogs = press_run_form_catalogs($pdo);
echo view('press-runs/partials/output-row.php', ['n' => $n, 'row' => $row, 'rowErrors' => [], 'outputItems' => $catalogs['outputItems'], 'vessels' => $catalogs['vessels'], 'locations' => $catalogs['locations']]);
