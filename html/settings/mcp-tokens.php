<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/mcp-tokens/queries.php';

$user = require_role();
$query = list_params('-created_at');
$data = [
    'result' => find_mcp_tokens_list(db(), $query['sort'], $query['page']),
    'query' => ['sort' => $query['sort']],
    'baseUrl' => (string) config('app.base_url', ''),
];

if (is_results_request('settings-mcp-tokens-results')) {
    header('Vary: HX-Request');
    echo view('mcp-tokens/partials/table.php', $data);
    exit;
}

// A token created on the previous request is shown exactly once, then forgotten.
$created = $_SESSION['mcp_token_created'] ?? null;
unset($_SESSION['mcp_token_created']);
if ($created !== null) {
    no_store_headers();
}
log_screen_entered('settings-mcp-tokens');
render_screen('AI access tokens', 'settings-mcp-tokens', view('mcp-tokens/page.php', $data + [
    'created' => $created,
    'input' => ['name' => '', 'scope' => 'activity'],
    'errors' => [],
]));
