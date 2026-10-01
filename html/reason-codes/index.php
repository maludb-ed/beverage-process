<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/reason-codes/queries.php';

$user = require_login();
$query = list_params('code');
$appliesTo = request_string('applies_to', 20);
$appliesTo = in_options($appliesTo, REASON_APPLIES_TO) ? $appliesTo : '';
$result = find_reason_codes(db(), $query['q'], $query['sort'], $query['page'], $appliesTo ?: null);
$data = ['result' => $result, 'query' => ['q' => $query['q'], 'sort' => $query['sort'], 'applies_to' => $appliesTo], 'canEdit' => user_can($user, 'compliance')];

if (is_results_request('reason-codes-list-results')) {
    header('Vary: HX-Request');
    echo view('reason-codes/partials/table.php', $data);
    exit;
}
log_screen_entered('reason-codes-list');
render_screen('Reason codes', 'reason-codes-list', view('reason-codes/page.php', $data));
