<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/activity/queries.php';

$user = require_login();
$q = request_string('q', 100);
$page = max(1, request_integer('page') ?? 1);
[$rows, $hasMore] = find_activity(db(), $q, $page);
$data = ['rows' => $rows, 'page' => $page, 'hasMore' => $hasMore, 'q' => $q];

// Search and pagination refresh only the results region (Pattern B inner swap).
if (is_htmx_request() && ($_SERVER['HTTP_HX_TARGET'] ?? '') === 'activity-list-results') {
    header('Vary: HX-Request');
    echo view('activity/partials/table.php', $data);
    exit;
}
log_screen_entered('activity-list');
render_screen('Activity', 'activity-list', view('activity/page.php', $data));
