<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/batches/queries.php';

// Pattern A fragment: one split output row (vessel + volume).
$user = require_role('production');
$n = preg_replace('/[^a-z0-9]/i', '', request_string('n', 20)) ?: 'n' . time();
echo view('batches/partials/split-row.php', ['n' => $n, 'row' => [], 'vessels' => batches_vessel_options(batches_vessel_catalog(db())), 'batches' => [], 'rowErrors' => [],
    'prefix' => 'batch-split-form-output-row', 'base' => 'outputs', 'kind' => 'split']);
