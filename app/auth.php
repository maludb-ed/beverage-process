<?php
declare(strict_types=1);

require_once __DIR__ . '/features/auth/queries.php';

const ROLE_RANK = ['viewer' => 0, 'compliance' => 1, 'quality' => 1, 'receiving' => 1, 'production' => 1, 'owner' => 9];

/** The signed-in user for this request, or null. Cached per request. */
function current_user(): ?array
{
    static $user = false;
    if ($user !== false) {
        return $user;
    }
    $id = $_SESSION['user_id'] ?? null;
    if ($id === null) {
        // The assistant acting for a user (localhost only, see verify_action_token()).
        $tokenUser = verify_action_token($_SERVER[ACTION_TOKEN_HEADER] ?? null);
        if ($tokenUser !== null) {
            $GLOBALS['__action_token_user'] = $tokenUser;
            $id = $tokenUser;
        }
    }
    if ($id === null) {
        return $user = null;
    }
    $found = find_user(db(), (int) $id);
    if ($found === null || $found['status'] !== 'active') {
        unset($_SESSION['user_id']);
        return $user = null;
    }
    return $user = $found;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        $next = $_SERVER['REQUEST_URI'] ?? '/';
        $target = '/login' . ($next !== '/' && !str_starts_with($next, '/login') ? '?next=' . rawurlencode($next) : '');
        redirect($target);
    }
    return $user;
}

/** Owner can do everything; otherwise the user's role must be one of $roles. */
function require_role(string ...$roles): array
{
    $user = require_login();
    if ($user['role'] === 'owner' || in_array($user['role'], $roles, true)) {
        return $user;
    }
    forbidden();
}

function user_can(array $user, string ...$roles): bool
{
    return $user['role'] === 'owner' || in_array($user['role'], $roles, true);
}

function normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/** Returns an error message or null when the password is acceptable. */
function password_problem(string $password): ?string
{
    $min = (int) config('security.password_min_length', 12);
    if (mb_strlen($password) < $min) {
        return "Use at least {$min} characters.";
    }
    if (strlen($password) > 72) {
        return 'Use at most 72 bytes.';
    }
    return null;
}

/**
 * Establish the session after any successful primary authentication.
 * Returns the path to continue to: the 2FA challenge when enabled, else $next.
 */
function begin_session_for(array $user, string $method, string $next = '/'): string
{
    $pdo = db();
    if ($user['totp_enabled_at'] !== null) {
        session_regenerate_id(true);
        $_SESSION['pending_2fa_user_id'] = (int) $user['id'];
        $_SESSION['pending_2fa_method']  = $method;
        $_SESSION['pending_2fa_until']   = time() + 60 * (int) config('security.pending_2fa_minutes', 10);
        $_SESSION['pending_2fa_next']    = $next;
        unset($_SESSION['user_id']);
        log_activity($pdo, 'login_2fa_pending', 'user', (int) $user['id'], $user['display_name'], null, null, ['method' => $method], 'login',
            'screen', (int) $user['id'], 'user/' . $user['id'] . ' ' . $user['display_name']);
        return '/login/2fa';
    }
    complete_login($user, $method);
    return $next;
}

function complete_login(array $user, string $method): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_method'], $_SESSION['pending_2fa_until'], $_SESSION['pending_2fa_next']);
    rotate_csrf_token();
    $pdo = db();
    touch_last_login($pdo, (int) $user['id']);
    log_activity($pdo, $method === 'totp' || $method === 'recovery' ? 'login_2fa' : ($method === 'google' ? 'login_google' : 'login'),
        'user', (int) $user['id'], $user['display_name'], null, null, ['method' => $method], 'login',
        'screen', (int) $user['id'], 'user/' . $user['id'] . ' ' . $user['display_name']);
}

function logout_user(): void
{
    $user = current_user();
    if ($user !== null) {
        log_activity(db(), 'logout', 'user', (int) $user['id'], $user['display_name'], null, null, [], 'logout');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/** Safe "next" path: same-origin absolute path only. */
function safe_next(?string $next): string
{
    if ($next === null || $next === '' || !str_starts_with($next, '/') || str_starts_with($next, '//') || str_contains($next, "\n")) {
        return '/';
    }
    return $next;
}

function generate_token(): string
{
    return bin2hex(random_bytes(32));
}

function token_hash(string $token): string
{
    return hash('sha256', $token);
}

function throttle_config(): array
{
    return [
        (int) config('security.lockout_attempts_per_email', 5),
        (int) config('security.lockout_attempts_per_ip', 20),
        (int) config('security.lockout_window_minutes', 15),
    ];
}

// Assistant action tokens ------------------------------------------------------------
// The assistant service performs actions by calling the app's own endpoints, as the
// user who spoke, through the localhost-only actions MCP server. PHP mints a short-lived
// HMAC token per command-bar message; the endpoints accept it in place of the session
// (and in place of CSRF, which protects cookie sessions) only from localhost.
// Format: base64url("{user_id}.{expires_unix}.{nonce}") . "." . base64url(hmac_sha256(payload, key))

const ACTION_TOKEN_HEADER = 'HTTP_X_ACTION_TOKEN';

function base64url_encode(string $bytes): string
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

function base64url_decode(string $text): string|false
{
    return base64_decode(strtr($text, '-_', '+/') . str_repeat('=', (4 - strlen($text) % 4) % 4), true);
}

function action_token_key(): string
{
    $key = (string) config('security.action_token_key');
    if (strlen($key) < 32) {
        throw new RuntimeException('security.action_token_key is not configured.');
    }
    return $key;
}

/** A token that lets the assistant act as $userId for $ttlSeconds. */
function mint_action_token(int $userId, int $ttlSeconds = 300): string
{
    $payload = base64url_encode($userId . '.' . (time() + $ttlSeconds) . '.' . bin2hex(random_bytes(8)));
    return $payload . '.' . base64url_encode(hash_hmac('sha256', $payload, action_token_key(), true));
}

/** The user id a valid token carries, or null. Only accepted from localhost. */
function verify_action_token(?string $token): ?int
{
    $remote = $_SERVER['REMOTE_ADDR'] ?? '';
    if ($token === null || $token === '' || !in_array($remote, ['127.0.0.1', '::1'], true)) {
        return null;
    }
    $parts = explode('.', $token);
    if (count($parts) !== 2) {
        return null;
    }
    [$payload, $signature] = $parts;
    $expected = base64url_encode(hash_hmac('sha256', $payload, action_token_key(), true));
    if (!hash_equals($expected, $signature)) {
        return null;
    }
    $decoded = base64url_decode($payload);
    if ($decoded === false || !preg_match('/^(\d+)\.(\d+)\.[a-f0-9]{16}$/', $decoded, $m) || (int) $m[2] < time()) {
        return null;
    }
    return (int) $m[1];
}

/** True when this request is the assistant acting through a valid action token. */
function is_action_token_request(): bool
{
    return ($GLOBALS['__action_token_user'] ?? null) !== null;
}
