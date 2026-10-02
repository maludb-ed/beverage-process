<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/planning/projection.php';

$user = require_login();
$level = request_string('demand', 10);
$level = isset(PLANNING_LEVELS[$level]) ? $level : 'standing';
$projection = planning_projection(db(), $level);
log_screen_entered('planning-production');
render_screen('Suggested production', 'planning-production', view('planning/production.php', ['p' => $projection, 'user' => $user, 'canPrice' => user_can($user, 'sales')]));
