<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/transfers/queries.php';

$user = require_login();
$query = list_params('-transferred_at');
$status = request_string('status', 20);
$status = in_options($status, TRANSFER_STATUSES) ? $status : '';
$result = find_transfers(db(), $query['q'], $status ?: null, $query['sort'], $query['page']);
$data = ['result' => $result, 'query' => ['q' => $query['q'], 'sort' => $query['sort'], 'status' => $status], 'canEdit' => user_can($user, 'receiving')];

if (is_results_request('transfers-list-results')) {
    header('Vary: HX-Request');
    echo view('transfers/partials/table.php', $data);
    exit;
}
log_screen_entered('transfers-list');
render_screen('Transfers', 'transfers-list', view('transfers/page.php', $data));
