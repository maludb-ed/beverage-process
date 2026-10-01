<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/batches/queries.php';

// Pattern A fragment: one blend input row (active batch + volume).
$user = require_role('production');
$n = preg_replace('/[^a-z0-9]/i', '', request_string('n', 20)) ?: 'n' . time();
echo view('batches/partials/split-row.php', ['n' => $n, 'row' => [], 'vessels' => [], 'batches' => batches_blend_input_options(db()), 'rowErrors' => [],
    'prefix' => 'batch-blend-form-input-row', 'base' => 'inputs', 'kind' => 'blend']);
