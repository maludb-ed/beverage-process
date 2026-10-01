<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/adjustments/queries.php';

$user = require_login();
$pdo = db();
$query = list_params('-adjusted_at');
$status = request_string('status', 20);
$status = in_options($status, ADJUSTMENT_STATUSES) ? $status : '';
$reasons = inventory_reason_options($pdo, 'adjustment');
$reasonId = request_integer('reason_code_id');
$reasonId = $reasonId !== null && isset($reasons[$reasonId]) ? $reasonId : null;
$result = find_adjustments($pdo, $query['q'], $status ?: null, $query['sort'], $query['page'], $reasonId);
$data = [
    'result' => $result, 'reasons' => $reasons,
    'query' => ['q' => $query['q'], 'sort' => $query['sort'], 'status' => $status, 'reason_code_id' => $reasonId],
    'canEdit' => user_can($user, 'receiving'),
];

if (is_results_request('adjustments-list-results')) {
    header('Vary: HX-Request');
    echo view('adjustments/partials/table.php', $data);
    exit;
}
log_screen_entered('adjustments-list');
render_screen('Adjustments', 'adjustments-list', view('adjustments/page.php', $data));
