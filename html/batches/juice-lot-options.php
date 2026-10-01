<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/batches/queries.php';

// Pattern A fragment: one juice row whose lot select lists released juice lots in a vessel or with stock.
$user = require_role('production');
$n = preg_replace('/[^a-z0-9]/i', '', request_string('n', 20)) ?: 'n' . time();
echo view('batches/partials/juice-row.php', ['n' => $n, 'row' => [], 'juiceLots' => find_juice_lot_options(db()), 'rowErrors' => []]);
