<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

no_store_headers();
$pdo = db();
$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$row = preg_match('/^[a-f0-9]{64}$/', $token) ? find_valid_token($pdo, 'invite', token_hash($token)) : null;
if ($row === null) {
    echo view('auth/layout.php', ['title' => 'Invitation', 'content' => view('auth/message.php', [
        'heading' => 'Invitation expired', 'message' => 'That invitation is no longer valid. Ask the owner to send a new one.'])]);
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
        update_user_password($pdo, (int) $row['user_id'], hash_password($password), true);
        consume_token($pdo, (int) $row['id']);
        log_activity($pdo, 'invite_accepted', 'user', (int) $row['user_id'], $user['display_name'] ?? null, null, null, [], 'invite',
            'screen', (int) $row['user_id'], 'user/' . $row['user_id'] . ' ' . ($user['display_name'] ?? ''));
        $pdo->commit();
        $user = find_user($pdo, (int) $row['user_id']);
        $target = begin_session_for($user, 'password', '/');
        header('Location: ' . $target, true, 303);
        exit;
    }
}
echo view('auth/layout.php', ['title' => 'Welcome', 'content' => view('auth/set-password.php', [
    'token' => $token, 'action' => '/invite/' . $token, 'heading' => 'Welcome to ' . config('app.name'), 'errors' => $errors])]);
