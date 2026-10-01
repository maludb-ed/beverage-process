<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/batches/queries.php';

$user = require_login();
$query = list_params('-started_at');
$status = request_string('status', 20);
$status = $status === 'all' || in_options($status, BATCH_STATUSES) ? $status : '';
$filter = $status === '' ? ['active'] : ($status === 'all' ? [] : [$status]);
$result = find_batches(db(), $query['q'], $filter, $query['sort'], $query['page']);
$data = ['result' => $result, 'query' => ['q' => $query['q'], 'sort' => $query['sort'], 'status' => $status], 'canEdit' => user_can($user, 'production')];

if (is_results_request('batches-list-results')) {
    header('Vary: HX-Request');
    echo view('batches/partials/table.php', $data);
    exit;
}
log_screen_entered('batches-list');
render_screen('Batches', 'batches-list', view('batches/page.php', $data));
