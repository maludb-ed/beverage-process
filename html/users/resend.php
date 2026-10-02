<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/users/queries.php';
require_once dirname(__DIR__, 2) . '/app/mail.php';

// Resend an invitation: a fresh 3-day link replaces any earlier one (earlier links stop working).
require_post();
verify_csrf();
$user = require_role();

$id = request_integer('id') ?? not_found('That user does not exist.');
$pdo = db();
$target = find_user($pdo, $id) ?? not_found('That user does not exist.');
$errors = [];

if ($target['status'] !== 'invited') {
    $errors['form'] = $target['display_name'] . ' has already accepted or is disabled, so there is no invitation to resend.';
} else {
    $token = generate_token();
    try {
        $pdo->beginTransaction();
        expire_tokens_for_user($pdo, $id, 'invite');
        insert_one_time_token($pdo, $id, 'invite', token_hash($token), 72 * 60);
        log_activity($pdo, 'user_invite_resent', 'user', $id, $target['display_name'], null, null, ['email' => $target['email']], 'users-list');
        $pdo->commit();
    } catch (PDOException $exception) {
        $pdo->rollBack();
        error_log($exception->getMessage());
        $errors['form'] = db_error_message($exception) ?? 'The invitation could not be resent.';
    }
    if ($errors === []) {
        $sent = send_mail($target['email'], 'You are invited to ' . config('app.name'), 'invite', [
            'name' => $target['display_name'],
            'url' => rtrim((string) config('app.base_url'), '/') . '/invite/' . $token,
            'days' => 3,
            'invitedBy' => $user['display_name'],
        ]);
        flash($sent ? 'success' : 'warning', $sent ? 'New invitation sent to ' . $target['email'] . '. Earlier links no longer work.' : 'A new link was created, but the invitation email could not be sent.');
        hx_trigger('userChanged');
        hx_location('/users/');
    }
}
$query = list_params('display_name');
http_response_code(422);
render_screen('Users', 'users-list', view('users/page.php', [
    'result' => find_users($pdo, $query['q'], $query['sort'], $query['page']),
    'query' => ['q' => $query['q'], 'sort' => $query['sort']], 'currentUserId' => (int) $user['id'], 'errors' => $errors,
]));
