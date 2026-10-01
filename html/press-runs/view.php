<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/press-runs/queries.php';

$user = require_login();
$pdo = db();
$id = request_integer('id') ?? not_found('That press run does not exist.');
$run = find_press_run($pdo, $id) ?? not_found('That press run does not exist.');
log_screen_entered('press-run-view', 'press_run', $id, $run['number']);
render_screen($run['number'], 'press-run-view', view('press-runs/partials/view.php', [
    'run' => $run, 'inputs' => find_press_run_inputs($pdo, $id), 'outputs' => find_press_run_outputs($pdo, $id), 'user' => $user,
]), 'press_run', $id);
