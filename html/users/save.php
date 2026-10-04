<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/users/queries.php';
require_once dirname(__DIR__, 2) . '/app/mail.php';

require_post();
os_refuse_if_managed();           // os-adoption: people are granted in the kernel
verify_csrf();
$user = require_role();

$id = request_integer('id');
$input = [
    'id' => $id,
    'email' => normalize_email(request_string('email', 200)),
    'display_name' => request_string('display_name', 120),
    'role' => request_string('role', 20),
];
$errors = [];
$pdo = db();
$before = null;
if ($id !== null) {
    $before = find_user($pdo, $id) ?? not_found('That user does not exist.');
    $input['email'] = $before['email'];
    $input['role'] = $before['role'];
    $input['status'] = $before['status'];
    $input['totp_enabled_at'] = $before['totp_enabled_at'];
}
if ($input['display_name'] === '') { $errors['display_name'] = 'Name is required.'; }
if ($id === null) {
    if ($input['email'] === '' || !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) { $errors['email'] = 'Enter a valid email address.'; }
    elseif (find_user_by_email($pdo, $input['email']) !== null) { $errors['email'] = 'A user with that email already exists.'; }
    if (!in_options($input['role'], USER_ROLES)) { $errors['role'] = 'Choose a role.'; }
}

if ($errors === []) {
    $token = null;
    try {
        $pdo->beginTransaction();
        if ($id === null) {
            $created = insert_invited_user($pdo, $input['email'], $input['display_name'], $input['role']);
            $token = generate_token();
            insert_one_time_token($pdo, (int) $created['id'], 'invite', token_hash($token), 72 * 60);
            log_activity($pdo, 'user_invited', 'user', (int) $created['id'], $created['display_name'], null, users_log_snapshot($created), [], 'user-add');
            $saved = $created;
        } else {
            $saved = update_user_profile($pdo, $id, $input['display_name']);
            log_activity($pdo, 'user_updated', 'user', $id, $saved['display_name'], users_log_snapshot($before), users_log_snapshot($saved), [], 'user-edit');
        }
        $pdo->commit();
    } catch (PDOException $exception) {
        $pdo->rollBack();
        error_log($exception->getMessage());
        $errors['form'] = (is_unique_violation($exception) ? 'A user with that email already exists.' : db_error_message($exception)) ?? 'The user could not be saved.';
    }
    if ($errors === []) {
        if ($token !== null) {
            $sent = send_mail($saved['email'], 'You are invited to ' . config('app.name'), 'invite', [
                'name' => $saved['display_name'],
                'url' => rtrim((string) config('app.base_url'), '/') . '/invite/' . $token,
                'days' => 3,
                'invitedBy' => $user['display_name'],
            ]);
            flash($sent ? 'success' : 'warning', $sent ? 'Invitation sent to ' . $saved['email'] . '.' : 'User created, but the invitation email could not be sent.');
        } else {
            flash('success', 'User "' . $saved['display_name'] . '" saved.');
        }
        hx_trigger('userChanged');
        hx_location('/users/');
    }
}
http_response_code(422);
render_screen($id ? 'Edit User' : 'Invite User', $id ? 'user-edit' : 'user-add', view('users/partials/form.php', ['target' => $input, 'errors' => $errors, 'currentUserId' => (int) $user['id']]), 'user', $id);
