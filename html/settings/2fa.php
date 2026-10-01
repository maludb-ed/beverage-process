<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

$user = require_login();   // conformance: self-service (the signed-in user's own account)
$pdo = db();
$errors = [];
$mode = $user['totp_enabled_at'] === null ? 'off' : 'on';
$recoveryCodes = [];
$qr = null;
$secret = null;

if (isset($_GET['cancel'])) {
    unset($_SESSION['totp_enroll_secret']);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $step = (string) ($_POST['step'] ?? '');
    if ($step === 'start' && $mode === 'off') {
        $_SESSION['totp_enroll_secret'] = totp_new_secret();   // in the session only until confirmed
    } elseif ($step === 'confirm' && $mode === 'off') {
        $pending = $_SESSION['totp_enroll_secret'] ?? null;
        $code = (string) ($_POST['code'] ?? '');
        if ($pending === null) {
            $errors[] = 'Start the enrollment again.';
        } elseif (totp_verify($pending, $code) === null) {
            $errors[] = 'That code did not match. Check the time on your phone and try again.';
        } else {
            $recoveryCodes = generate_recovery_codes();
            $pdo->beginTransaction();
            enable_totp($pdo, (int) $user['id'], totp_encrypt_secret($pending));
            replace_recovery_codes($pdo, (int) $user['id'], array_map('hash_password', $recoveryCodes));
            log_activity($pdo, 'totp_enabled', 'user', (int) $user['id'], $user['display_name']);
            $pdo->commit();
            unset($_SESSION['totp_enroll_secret']);
            $user = find_user($pdo, (int) $user['id']);
            $mode = 'codes';
        }
    } elseif ($step === 'disable' && $mode === 'on') {
        $code = strtoupper(trim((string) ($_POST['code'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        $authenticated = $user['password_hash'] === null || password_verify($password, $user['password_hash']);
        $codeOk = false;
        if (preg_match('/^\d{6}$/', str_replace(' ', '', $code))) {
            $ts = totp_verify(totp_decrypt_secret($user['totp_secret']), str_replace(' ', '', $code));
            $codeOk = $ts !== null && claim_totp_timestep($pdo, (int) $user['id'], $ts);
        } else {
            foreach (unused_recovery_codes($pdo, (int) $user['id']) as $row) {
                if (password_verify($code, $row['code_hash'])) {
                    $codeOk = true;
                    break;
                }
            }
        }
        if (!$authenticated) {
            $errors[] = 'Your password is not correct.';
        } elseif (!$codeOk) {
            $errors[] = 'That code is not valid.';
        } else {
            $pdo->beginTransaction();
            disable_totp($pdo, (int) $user['id']);
            log_activity($pdo, 'totp_disabled', 'user', (int) $user['id'], $user['display_name']);
            $pdo->commit();
            $user = find_user($pdo, (int) $user['id']);
            $mode = 'off';
        }
    }
}
if ($mode === 'off' && !empty($_SESSION['totp_enroll_secret'])) {
    $mode = 'enrolling';
    $secret = $_SESSION['totp_enroll_secret'];
    $qr = totp_qr_data_uri(totp_provisioning_uri($secret, $user['email']));
}
$codesLeft = $mode === 'on' ? count(unused_recovery_codes($pdo, (int) $user['id'])) : 0;
log_screen_entered('settings-2fa', 'user', (int) $user['id'], $user['display_name']);
render_screen('Two-factor authentication', 'settings-2fa', view('settings/2fa.php', [
    'user' => $user, 'mode' => $mode, 'qr' => $qr, 'secret' => $secret, 'errors' => $errors, 'recoveryCodes' => $recoveryCodes, 'codesLeft' => $codesLeft,
]), 'user', (int) $user['id']);
