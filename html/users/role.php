<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/users/queries.php';

require_post();
verify_csrf();
$user = require_role();

$id = request_integer('id') ?? not_found('That user does not exist.');
$role = request_string('role', 20);
$pdo = db();
$errors = [];
$target = find_user($pdo, $id) ?? not_found('That user does not exist.');
if (!in_options($role, USER_ROLES)) { $errors['role'] = 'Choose a role.'; }

if ($errors === []) {
    try {
        $pdo->beginTransaction();
        $before = find_user($pdo, $id);
        if ($before['role'] === 'owner' && $before['status'] === 'active' && $role !== 'owner' && count_active_owners($pdo) <= 1) {
            $pdo->rollBack();
            $errors['role'] = 'The last active owner cannot be demoted. Make another user an owner first.';
        } else {
            $updated = update_user_role($pdo, $id, $role);
            log_activity($pdo, 'user_role_set', 'user', $id, $updated['display_name'], users_log_snapshot($before), users_log_snapshot($updated), [], 'user-edit');
            $pdo->commit();
            flash('success', 'Role for "' . $updated['display_name'] . '" set to ' . USER_ROLES[$role] . '.');
            hx_trigger('userChanged');
            hx_location('/users/');
        }
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        error_log($exception->getMessage());
        $errors['form'] = db_error_message($exception) ?? 'The role could not be changed.';
    }
}
http_response_code(422);
render_screen('Edit User', 'user-edit', view('users/partials/form.php', ['target' => $target, 'errors' => $errors, 'currentUserId' => (int) $user['id']]), 'user', $id);
