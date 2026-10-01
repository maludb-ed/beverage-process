<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/receipts/queries.php';

$user = require_login();
$query = list_params('-received_at');
$status = request_string('status', 20);
$status = in_options($status, RECEIPT_STATUSES) ? $status : '';
$result = find_receipts(db(), $query['q'], $query['sort'], $query['page'], $status ?: null);
$data = ['result' => $result, 'query' => ['q' => $query['q'], 'sort' => $query['sort'], 'status' => $status], 'canEdit' => user_can($user, 'receiving')];

if (is_results_request('receipts-list-results')) {
    header('Vary: HX-Request');
    echo view('receipts/partials/table.php', $data);
    exit;
}
log_screen_entered('receipts-list');
render_screen('Receipts', 'receipts-list', view('receipts/page.php', $data));
