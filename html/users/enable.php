<?php
declare(strict_types=1);
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';
require_once dirname(__DIR__, 2) . '/app/features/users/queries.php';

// Re-enable a disabled user. Someone who has signed in before (password set or a past login)
// goes back to active; someone who never accepted their invitation goes back to invited, and
// the owner then uses Resend invitation (disabling cancelled their old link).
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
    if ($before['status'] !== 'disabled') {
        $errors['form'] = 'That user is not disabled.';
    } else {
        $status = ($before['password_hash'] ?? null) !== null || $before['last_login_at'] !== null ? 'active' : 'invited';
        $updated = update_user_status($pdo, $id, $status);
        log_activity($pdo, 'user_enabled', 'user', $id, $updated['display_name'], users_log_snapshot($before), users_log_snapshot($updated), [], 'users-list');
        $pdo->commit();
        flash('success', $status === 'active'
            ? 'User "' . $updated['display_name'] . '" enabled. They can sign in again.'
            : 'User "' . $updated['display_name'] . '" enabled as invited. Use Resend invitation to send them a new link.');
        hx_trigger('userChanged');
        hx_location('/users/');
    }
    $pdo->rollBack();
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    error_log($exception->getMessage());
    $errors['form'] = db_error_message($exception) ?? 'The user could not be enabled.';
}
$query = list_params('display_name');
http_response_code(422);
render_screen('Users', 'users-list', view('users/page.php', [
    'result' => find_users($pdo, $query['q'], $query['sort'], $query['page']),
    'query' => ['q' => $query['q'], 'sort' => $query['sort']], 'currentUserId' => (int) $user['id'], 'errors' => $errors,
]));
