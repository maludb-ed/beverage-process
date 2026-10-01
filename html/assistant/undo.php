<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

// The command bar's Undo button: POST undo_id (session user, CSRF). The undo rules live in
// one place, the actions MCP server (services/actions_mcp/undo.py); this endpoint asks it
// through its localhost JSON route POST /undo with an action token minted for the session
// user, and renders the outcome in the reply bubble. The actions server performs the undo
// by calling this app's own endpoints, which log it like any other action.
$user = require_login();
require_post();
verify_csrf();

$raw = request_string('undo_id', 20);
$undoId = ctype_digit($raw) ? (int) $raw : null;
if ($undoId === null) {
    http_response_code(422);
    echo view('assistant/reply.php', ['message' => 'Undo', 'reply' => 'There is nothing to undo.', 'undoId' => null]);
    exit;
}

$url = rtrim((string) config('assistant.actions_url', 'http://127.0.0.1:8703'), '/') . '/undo';
$curl = curl_init($url);
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode(['undo_id' => $undoId], JSON_THROW_ON_ERROR),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'X-Action-Token: ' . mint_action_token((int) $user['id'], 120)],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 3,
    CURLOPT_TIMEOUT => 45,
]);
$body = curl_exec($curl);
$status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
$curlError = curl_error($curl);
curl_close($curl);

$result = is_string($body) ? json_decode($body, true) : null;
if (!is_array($result)) {
    error_log('assistant undo: actions server unavailable (' . $status . ' ' . $curlError . ')');
    http_response_code(503);
    echo view('assistant/reply.php', ['message' => 'Undo', 'reply' => 'Undo is not available right now; nothing was changed.', 'undoId' => null]);
    exit;
}

$reply = match ($result['status'] ?? '') {
    'success' => (string) ($result['done'] ?? 'Undone.'),
    'needs_confirmation' => (string) ($result['summary'] ?? 'This undo needs confirmation.') . ' Say "yes, undo it" in the command bar to confirm.',
    default => (string) ($result['message'] ?? 'That could not be undone.'),
};
if (($result['status'] ?? '') === 'success' && !empty($result['refresh']) && is_array($result['refresh'])) {
    hx_trigger(implode(', ', array_map('strval', $result['refresh'])));
}
echo view('assistant/reply.php', ['message' => 'Undo', 'reply' => $reply, 'undoId' => null]);
