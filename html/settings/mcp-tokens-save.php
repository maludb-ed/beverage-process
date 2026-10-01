<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/mcp-tokens/queries.php';

require_post();
verify_csrf();
$user = require_role();

$input = ['name' => request_string('name', 80), 'scope' => request_string('scope', 20)];
$pdo = db();
$errors = [];
if ($input['name'] === '') {
    $errors['name'] = 'Name the token after the tool or person using it (e.g. "Ed laptop Claude Desktop").';
} elseif (strcasecmp($input['name'], MCP_TOKEN_SERVICE_NAME) === 0) {
    $errors['name'] = '"' . MCP_TOKEN_SERVICE_NAME . '" is reserved for the built-in assistant.';
} elseif (mcp_token_name_in_use($pdo, $input['name'])) {
    $errors['name'] = 'An active token already has that name; names identify the tool in the activity log.';
}
if (!in_options($input['scope'], MCP_TOKEN_SCOPES)) {
    $errors['scope'] = 'Choose what the token can read.';
}

if ($errors === []) {
    try {
        $token = generate_token();   // 64 hex characters from random_bytes(32)
        $pdo->beginTransaction();
        $saved = insert_mcp_token($pdo, $input['name'], $input['scope'], $token, (int) $user['id']);
        log_activity($pdo, 'mcp_token_created', 'mcp_token', (int) $saved['id'], $saved['name'],
            null, ['name' => $saved['name'], 'scope' => $saved['scope'], 'token_prefix' => $saved['token_prefix']], [], 'settings-mcp-tokens');
        $pdo->commit();
        $_SESSION['mcp_token_created'] = ['id' => (int) $saved['id'], 'name' => $saved['name'], 'scope' => $saved['scope'], 'token' => $token];
        flash('success', 'Token "' . $saved['name'] . '" created. Copy it now; it will not be shown again.');
        hx_trigger('mcpTokensChanged');
        hx_location('/settings/mcp-tokens');
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $errors['form'] = db_error_message($exception) ?? 'The token could not be created.';
    }
}
http_response_code(422);
$query = list_params('-created_at');
render_screen('AI access tokens', 'settings-mcp-tokens', view('mcp-tokens/page.php', [
    'result' => find_mcp_tokens_list($pdo, $query['sort'], $query['page']),
    'query' => ['sort' => $query['sort']],
    'baseUrl' => (string) config('app.base_url', ''),
    'created' => null,
    'input' => $input,
    'errors' => $errors,
]));
