<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/users/queries.php';

require_post();
os_refuse_if_managed();           // os-adoption: people are granted in the kernel
verify_csrf();
$user = require_role();

$id = request_integer('id') ?? not_found('That user does not exist.');
$pdo = db();
find_user($pdo, $id) ?? not_found('That user does not exist.');
$errors = [];

try {
    $pdo->beginTransaction();
    $before = find_user($pdo, $id);
    if ($id === (int) $user['id']) {
        $errors['form'] = 'You cannot disable your own account.';
    } elseif ($before['status'] === 'disabled') {
        $errors['form'] = 'That user is already disabled.';
    } elseif ($before['role'] === 'owner' && $before['status'] === 'active' && count_active_owners($pdo) <= 1) {
        $errors['form'] = 'The last active owner cannot be disabled.';
    }
    if ($errors === []) {
        $updated = update_user_status($pdo, $id, 'disabled');
        expire_tokens_for_user($pdo, $id, 'invite');
        expire_tokens_for_user($pdo, $id, 'password_reset');
        log_activity($pdo, 'user_disabled', 'user', $id, $updated['display_name'], users_log_snapshot($before), users_log_snapshot($updated), [], 'users-list');
        $pdo->commit();
        flash('success', 'User "' . $updated['display_name'] . '" disabled.');
        hx_trigger('userChanged');
        hx_location('/users/');
    }
    $pdo->rollBack();
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($exception->getMessage());
    $errors['form'] = db_error_message($exception) ?? 'The user could not be disabled.';
}
$query = list_params('display_name');
http_response_code(422);
render_screen('Users', 'users-list', view('users/page.php', [
    'result' => find_users($pdo, $query['q'], $query['sort'], $query['page']),
    'query' => ['q' => $query['q'], 'sort' => $query['sort']], 'currentUserId' => (int) $user['id'], 'errors' => $errors,
]));
