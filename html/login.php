<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

no_store_headers();
if (current_user() !== null) {
    redirect('/');
}
$next = safe_next($_POST['next'] ?? $_GET['next'] ?? '/');
$googleEnabled = (string) config('google.client_id') !== '';
$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email    = normalize_email((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $ip       = client_ip();
    $pdo      = db();
    [$perEmail, $perIp, $window] = throttle_config();

    if (too_many_attempts($pdo, $email, $ip, $perEmail, $perIp, $window)) {
        http_response_code(429);
        record_login_attempt($pdo, $email, $ip, 'password', false);
        $error = 'Too many attempts. Try again in a few minutes.';
    } else {
        $user = find_user_by_email($pdo, $email);
        $hash = $user['password_hash'] ?? (string) config('security.dummy_password_hash');
        $ok = password_verify($password, $hash) && $user !== null && $user['password_hash'] !== null && $user['status'] === 'active';
        record_login_attempt($pdo, $email, $ip, 'password', $ok);
        if ($ok) {
            if (password_needs_rehash($user['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
                update_user_password($pdo, (int) $user['id'], hash_password($password));
            }
            $target = begin_session_for($user, 'password', $next);
            header('Location: ' . $target, true, 303);
            exit;
        }
        $error = 'Invalid email or password.';
    }
}

echo view('auth/layout.php', ['title' => 'Sign in', 'content' => view('auth/login.php', [
    'email' => $email, 'error' => $error, 'next' => $next, 'googleEnabled' => $googleEnabled,
])]);
