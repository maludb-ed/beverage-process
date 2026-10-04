<?php
declare(strict_types=1);
/** /sso/logout — the kernel's sign-out notice: end every session of the member; 204 whether or not it verified. */
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
if (!os_enabled()) {
    not_found();
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}
$pdo = db();
$notice = (string) ($_POST['notice'] ?? '');
if ($notice === '' && is_array($body = json_decode((string) file_get_contents('php://input'), true))) {
    $notice = (string) ($body['notice'] ?? '');
}
$memberId = $notice === '' ? null : verify_sso_logout_notice($notice, os_app_key());
if ($memberId !== null) {
    $ended = os_end_member_sessions($pdo, $memberId, 'kernel');
    $user = os_user_for_member($pdo, $memberId);
    log_activity($pdo, 'os_sign_out', 'user', $user !== null ? (int) $user['id'] : null, $user['display_name'] ?? null, null, ['sessions' => $ended], ['by' => 'kernel'], 'sso', 'web', null, 'os/member:' . $memberId);
}
header_remove('Set-Cookie');
http_response_code(204);
