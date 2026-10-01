<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/press-runs/queries.php';

// Pattern A fragment: one fruit-in row whose lot select lists released fruit lots with stock.
$user = require_role('production');
$n = preg_replace('/[^a-z0-9]/i', '', request_string('n', 20)) ?: 'n' . time();
echo view('press-runs/partials/input-row.php', ['n' => $n, 'row' => [], 'fruitLots' => find_fruit_lot_options(db()), 'rowErrors' => []]);
