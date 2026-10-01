<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

no_store_headers();
$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = normalize_email((string) ($_POST['email'] ?? ''));
    $ip = client_ip();
    $pdo = db();
    [$perEmail, $perIp, $window] = throttle_config();
    if (!too_many_attempts($pdo, $email, $ip, $perEmail, $perIp, $window)) {
        record_login_attempt($pdo, $email, $ip, 'reset', true);
        $user = find_user_by_email($pdo, $email);
        if ($user !== null && $user['status'] === 'active') {
            $token = generate_token();
            $minutes = (int) config('security.reset_token_minutes', 60);
            expire_tokens_for_user($pdo, (int) $user['id'], 'password_reset');
            insert_one_time_token($pdo, (int) $user['id'], 'password_reset', token_hash($token), $minutes);
            send_mail($user['email'], 'Reset your ' . config('app.name') . ' password', 'password-reset', [
                'name' => $user['display_name'], 'url' => rtrim((string) config('app.base_url'), '/') . '/password/reset/' . $token, 'minutes' => $minutes,
            ]);
            log_activity($pdo, 'password_reset_requested', 'user', (int) $user['id'], $user['display_name'], null, null, [], 'password-reset',
                'screen', (int) $user['id'], 'user/' . $user['id'] . ' ' . $user['display_name']);
        }
    }
    $sent = true;   // always the same answer: no enumeration
}
echo view('auth/layout.php', ['title' => 'Reset password', 'content' => view('auth/reset-request.php', ['sent' => $sent])]);
