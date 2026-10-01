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
