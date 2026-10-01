<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/standard-costs/queries.php';
require_once dirname(__DIR__, 2) . '/app/features/premises/queries.php';

$user = require_role();
$pdo = db();
$premises = premises_options($pdo);
$input = ['premises_id' => request_integer('premises_id') ?? (count($premises) === 1 ? array_key_first($premises) : null), 'rate' => '', 'effective_from' => today()];
log_screen_entered('standard-cost-overhead', 'overhead_rate', null, null);
render_screen('Set Overhead Rate', 'standard-cost-overhead', view('standard-costs/partials/overhead-form.php', ['input' => $input, 'errors' => [], 'premises' => $premises]), 'overhead_rate');
