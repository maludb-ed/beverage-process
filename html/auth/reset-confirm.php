<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

no_store_headers();
$pdo = db();
$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$row = preg_match('/^[a-f0-9]{64}$/', $token) ? find_valid_token($pdo, 'password_reset', token_hash($token)) : null;
if ($row === null) {
    echo view('auth/layout.php', ['title' => 'Reset password', 'content' => view('auth/message.php', [
        'heading' => 'Link expired', 'message' => 'That reset link is no longer valid. Request a new one from the sign-in page.'])]);
    exit;
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    if (($problem = password_problem($password)) !== null) {
        $errors[] = $problem;
    }
    if ($password !== (string) ($_POST['password_confirm'] ?? '')) {
        $errors[] = 'The two passwords do not match.';
    }
    if ($errors === []) {
        $user = find_user($pdo, (int) $row['user_id']);
        $pdo->beginTransaction();
        update_user_password($pdo, (int) $row['user_id'], hash_password($password));
        consume_token($pdo, (int) $row['id']);
        log_activity($pdo, 'password_reset', 'user', (int) $row['user_id'], $user['display_name'] ?? null, null, null, [], 'password-reset',
            'screen', (int) $row['user_id'], 'user/' . $row['user_id'] . ' ' . ($user['display_name'] ?? ''));
        $pdo->commit();
        flash('success', 'Your password was changed. Sign in with it now.');
        header('Location: /login', true, 303);
        exit;
    }
}
echo view('auth/layout.php', ['title' => 'Reset password', 'content' => view('auth/set-password.php', [
    'token' => $token, 'action' => '/password/reset/' . $token, 'heading' => 'Choose a new password', 'errors' => $errors])]);
