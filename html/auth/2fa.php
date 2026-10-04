<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
os_close_local_signin();            // os-adoption: the kernel signs people in while OS_ENABLED is on

no_store_headers();
$pendingId = $_SESSION['pending_2fa_user_id'] ?? null;
if ($pendingId === null || ($_SESSION['pending_2fa_until'] ?? 0) < time()) {
    unset($_SESSION['pending_2fa_user_id'], $_SESSION['pending_2fa_until'], $_SESSION['pending_2fa_next'], $_SESSION['pending_2fa_method']);
    flash('warning', 'Please sign in again.');
    header('Location: /login', true, 303);
    exit;
}
$pdo = db();
$user = find_user($pdo, (int) $pendingId);
if ($user === null || $user['status'] !== 'active' || $user['totp_enabled_at'] === null) {
    header('Location: /login', true, 303);
    exit;
}
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
    $ip   = client_ip();
    [$perEmail, $perIp, $window] = throttle_config();
    if (too_many_attempts($pdo, $user['email'], $ip, $perEmail, $perIp, $window)) {
        http_response_code(429);
        record_login_attempt($pdo, $user['email'], $ip, 'totp', false);
        $error = 'Too many attempts. Try again in a few minutes.';
    } else {
        $method = null;
        if (preg_match('/^\d{6}$/', str_replace(' ', '', $code))) {
            $timestep = totp_verify(totp_decrypt_secret($user['totp_secret']), str_replace(' ', '', $code));
            if ($timestep !== null && claim_totp_timestep($pdo, (int) $user['id'], $timestep)) {
                $method = 'totp';
            }
        } else {
            foreach (unused_recovery_codes($pdo, (int) $user['id']) as $row) {
                if (password_verify($code, $row['code_hash'])) {
                    mark_recovery_code_used($pdo, (int) $row['id']);
                    $method = 'recovery';
                    break;
                }
            }
        }
        record_login_attempt($pdo, $user['email'], $ip, $method ?? 'totp', $method !== null);
        if ($method !== null) {
            $next = safe_next($_SESSION['pending_2fa_next'] ?? '/');
            complete_login($user, $method);
            if ($method === 'recovery') {
                $left = count(unused_recovery_codes($pdo, (int) $user['id']));
                flash($left <= 2 ? 'warning' : 'info', "You signed in with a recovery code. {$left} remain.");
            }
            header('Location: ' . $next, true, 303);
            exit;
        }
        $error = 'That code is not valid.';
    }
}
echo view('auth/layout.php', ['title' => 'Two-factor code', 'content' => view('auth/2fa.php', ['error' => $error])]);
