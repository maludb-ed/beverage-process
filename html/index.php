<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';
require_once dirname(__DIR__) . '/app/features/dashboard/queries.php';
require_once dirname(__DIR__) . '/app/features/activity/queries.php';
require_once dirname(__DIR__) . '/app/features/orders/fulfillment.php';
require_once dirname(__DIR__) . '/app/features/planning/projection.php';

$user = require_login();
log_screen_entered('dashboard');
$pdo = db();
render_screen('Dashboard', 'dashboard', view('dashboard/page.php', [
    'stats'  => dashboard_stats($pdo),
    'recent' => recent_activity($pdo, 10),
    'orders' => orders_dashboard_stats($pdo),
    'planning' => planning_dashboard_stats($pdo),
]));
