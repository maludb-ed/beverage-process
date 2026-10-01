<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/mcp-tokens/queries.php';

// POST /settings/mcp-tokens/{id}/revoke: the router passes the number after
// "mcp-tokens" as sub_id (the feature is "settings").
require_post();
verify_csrf();
$user = require_role();

$pdo = db();
$id = request_integer('sub_id') ?? request_integer('id') ?? not_found('That token does not exist.');
$token = find_mcp_token($pdo, $id) ?? not_found('That token does not exist.');
if (mcp_token_is_service($token)) {
    forbidden('The built-in assistant uses this token; it cannot be revoked here.');
}
if ($token['revoked_at'] !== null) {
    flash('info', 'Token "' . $token['name'] . '" was already revoked.');
    hx_location('/settings/mcp-tokens');
}
try {
    $pdo->beginTransaction();
    $revoked = revoke_mcp_token($pdo, $id, (int) $user['id']);
    if ($revoked === null) {
        throw new RuntimeException('The token was revoked by someone else just now.');
    }
    log_activity($pdo, 'mcp_token_revoked', 'mcp_token', $id, $token['name'],
        ['revoked_at' => null, 'scope' => $token['scope'], 'token_prefix' => $token['token_prefix']],
        ['revoked_at' => $revoked['revoked_at']], [], 'settings-mcp-tokens');
    $pdo->commit();
    flash('success', 'Token "' . $token['name'] . '" revoked. Tools using it lose access immediately.');
    hx_trigger('mcpTokensChanged');
} catch (RuntimeException | PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('mcp token revoke failed: ' . $exception->getMessage());
    flash('error', $exception instanceof PDOException ? (db_error_message($exception) ?? 'The token could not be revoked.') : $exception->getMessage());
}
hx_location('/settings/mcp-tokens');
