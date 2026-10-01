<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

$user = require_login();   // conformance: self-service (the signed-in user's own account)
$pdo = db();
$errors = [];
$saved = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $displayName = request_string('display_name', 120);
    $current = (string) ($_POST['current_password'] ?? '');
    $new     = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    if ($displayName === '') {
        $errors[] = 'Name is required.';
    }
    $changePassword = $new !== '' || $confirm !== '' || $current !== '';
    if ($changePassword) {
        if ($user['password_hash'] === null) {
            $errors[] = 'This account signs in with Google; use the password reset link to add a password.';
        } elseif (!password_verify($current, $user['password_hash'])) {
            $errors[] = 'The current password is not correct.';
        }
        if (($problem = password_problem($new)) !== null) {
            $errors[] = 'New password: ' . $problem;
        }
        if ($new !== $confirm) {
            $errors[] = 'The new passwords do not match.';
        }
    }
    if ($errors === []) {
        $pdo->beginTransaction();
        $before = ['display_name' => $user['display_name']];
        $updated = update_user_profile($pdo, (int) $user['id'], $displayName);
        log_activity($pdo, 'profile_updated', 'user', (int) $user['id'], $displayName, $before, ['display_name' => $displayName]);
        if ($changePassword) {
            update_user_password($pdo, (int) $user['id'], hash_password($new));
            log_activity($pdo, 'password_changed', 'user', (int) $user['id'], $displayName);
        }
        $pdo->commit();
        $user = find_user($pdo, (int) $user['id']);
        $saved = $changePassword ? 'Profile and password saved.' : 'Profile saved.';
    }
}
log_screen_entered('settings-profile', 'user', (int) $user['id'], $user['display_name']);
render_screen('My profile', 'settings-profile', view('settings/profile.php', ['user' => $user, 'errors' => $errors, 'saved' => $saved]), 'user', (int) $user['id']);
