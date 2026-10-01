<?php
declare(strict_types=1);

// Users admin. find_user, find_user_by_email, insert_user, update_user_profile, insert_one_time_token
// and expire_tokens_for_user live in features/auth/queries.php (always loaded).

const USER_ROLES = ['owner' => 'Owner', 'production' => 'Production', 'receiving' => 'Receiving', 'quality' => 'Quality', 'compliance' => 'Compliance', 'viewer' => 'Viewer'];
const USER_SORTS = ['display_name' => 'u.display_name', 'email' => 'lower(u.email)', 'role' => 'u.role', 'status' => 'u.status', 'last_login_at' => 'u.last_login_at'];
const USERS_LIST_COLUMNS = 'u.id, u.email, u.display_name, u.role, u.status, u.last_login_at, u.totp_enabled_at, u.created_at';

function find_users(PDO $pdo, string $search = '', string $sort = 'display_name', int $page = 1): array
{
    $where = '';
    $params = [];
    if ($search !== '') {
        $where = ' WHERE u.display_name ILIKE :s OR u.email ILIKE :s';
        $params['s'] = '%' . $search . '%';
    }
    return paged_query(
        $pdo,
        'SELECT ' . USERS_LIST_COLUMNS . ' FROM app.users u' . $where . ' ORDER BY ' . order_by($sort, USER_SORTS, 'display_name'),
        'SELECT count(*) FROM app.users u' . $where,
        $params,
        $page
    );
}

/** Safe snapshot for the activity log: never includes password hash, TOTP secret or tokens. */
function users_log_snapshot(?array $user): ?array
{
    return $user === null ? null : array_intersect_key($user, array_flip(['id', 'email', 'display_name', 'role', 'status']));
}

function insert_invited_user(PDO $pdo, string $email, string $displayName, string $role): array
{
    return insert_user($pdo, $email, $displayName, $role, 'invited', null, false);
}

function update_user_role(PDO $pdo, int $id, string $role): array
{
    $statement = $pdo->prepare('UPDATE app.users SET role = :role WHERE id = :id RETURNING id, email, display_name, role, status');
    $statement->execute(['role' => $role, 'id' => $id]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('User not found.');
    }
    return $row;
}

function update_user_status(PDO $pdo, int $id, string $status): array
{
    $statement = $pdo->prepare('UPDATE app.users SET status = :status WHERE id = :id RETURNING id, email, display_name, role, status');
    $statement->execute(['status' => $status, 'id' => $id]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('User not found.');
    }
    return $row;
}

/** Active owners, row-locked so concurrent demotions cannot both pass the last-owner check. Call inside a transaction. */
function count_active_owners(PDO $pdo): int
{
    return count($pdo->query("SELECT id FROM app.users WHERE role = 'owner' AND status = 'active' FOR UPDATE")->fetchAll());
}
