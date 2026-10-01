<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/users/queries.php';

$user = require_role();
$id = request_integer('id');
if ($id !== null) {
    $target = find_user(db(), $id) ?? not_found('That user does not exist.');
    $screen = 'user-edit';
} else {
    $role = request_string('role', 20);
    $target = ['email' => request_string('email', 200), 'display_name' => '', 'role' => in_options($role, USER_ROLES) ? $role : 'viewer'];
    $screen = 'user-add';
}
log_screen_entered($screen, 'user', $id, $target['display_name'] ?: null);
render_screen($id ? 'Edit User' : 'Invite User', $screen, view('users/partials/form.php', ['target' => $target, 'errors' => [], 'currentUserId' => (int) $user['id']]), 'user', $id);
