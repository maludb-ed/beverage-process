<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/counts/queries.php';

$user = require_login();
$query = list_params('-started_at');
$status = request_string('status', 20);
$status = in_options($status, COUNT_STATUSES) ? $status : '';
$kind = request_string('kind', 20);
$kind = in_options($kind, COUNT_KINDS) ? $kind : '';
$result = find_counts(db(), $query['q'], $status ?: null, $query['sort'], $query['page'], $kind ?: null);
$data = ['result' => $result, 'query' => ['q' => $query['q'], 'sort' => $query['sort'], 'status' => $status, 'kind' => $kind], 'canEdit' => user_can($user, 'receiving')];

if (is_results_request('counts-list-results')) {
    header('Vary: HX-Request');
    echo view('counts/partials/table.php', $data);
    exit;
}
log_screen_entered('counts-list');
render_screen('Counts', 'counts-list', view('counts/page.php', $data));
