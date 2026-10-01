<?php
declare(strict_types=1);

// Auth data access: users, identities, login attempts, tokens, recovery codes.

const USER_COLUMNS = 'id, email, display_name, password_hash, role, status, email_verified_at, totp_secret, totp_enabled_at, totp_last_timestep, last_login_at, created_at, updated_at';

function find_user(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT ' . USER_COLUMNS . ' FROM app.users WHERE id = :id');
    $statement->execute(['id' => $id]);
    $user = $statement->fetch();
    return $user === false ? null : $user;
}

function find_user_by_email(PDO $pdo, string $email): ?array
{
    $statement = $pdo->prepare('SELECT ' . USER_COLUMNS . ' FROM app.users WHERE lower(email) = lower(:email)');
    $statement->execute(['email' => $email]);
    $user = $statement->fetch();
    return $user === false ? null : $user;
}

function insert_user(PDO $pdo, string $email, string $displayName, string $role, string $status, ?string $passwordHash, bool $emailVerified): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.users (email, display_name, role, status, password_hash, email_verified_at)
        VALUES (:email, :display_name, :role, :status, :password_hash, CASE WHEN :verified THEN now() ELSE NULL END)
        RETURNING id, email, display_name, role, status
    SQL);
    $statement->execute([
        'email' => $email, 'display_name' => $displayName, 'role' => $role, 'status' => $status,
        'password_hash' => $passwordHash, 'verified' => $emailVerified ? 't' : 'f',
    ]);
    return $statement->fetch();
}

function update_user_password(PDO $pdo, int $id, string $passwordHash, bool $activate = false): void
{
    $sql = $activate
        ? "UPDATE app.users SET password_hash = :hash, status = 'active', email_verified_at = COALESCE(email_verified_at, now()) WHERE id = :id"
        : 'UPDATE app.users SET password_hash = :hash WHERE id = :id';
    $pdo->prepare($sql)->execute(['hash' => $passwordHash, 'id' => $id]);
}

function update_user_profile(PDO $pdo, int $id, string $displayName): array
{
    $statement = $pdo->prepare('UPDATE app.users SET display_name = :name WHERE id = :id RETURNING id, email, display_name, role, status');
    $statement->execute(['name' => $displayName, 'id' => $id]);
    return $statement->fetch();
}

function touch_last_login(PDO $pdo, int $id): void
{
    $pdo->prepare('UPDATE app.users SET last_login_at = now() WHERE id = :id')->execute(['id' => $id]);
}

// Throttling ---------------------------------------------------------------------

function record_login_attempt(PDO $pdo, ?string $email, ?string $ip, string $kind, bool $succeeded): void
{
    $pdo->prepare('INSERT INTO app.login_attempts (email, ip, kind, succeeded) VALUES (:email, :ip, :kind, :ok)')
        ->execute(['email' => $email, 'ip' => $ip, 'kind' => $kind, 'ok' => $succeeded ? 't' : 'f']);
}

function too_many_attempts(PDO $pdo, ?string $email, ?string $ip, int $perEmail, int $perIp, int $windowMinutes): bool
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT
            count(*) FILTER (WHERE email = :email) AS by_email,
            count(*) FILTER (WHERE ip = :ip::inet) AS by_ip
        FROM app.login_attempts
        WHERE NOT succeeded AND attempted_at > now() - make_interval(mins => :window)
    SQL);
    $statement->execute(['email' => $email, 'ip' => $ip, 'window' => $windowMinutes]);
    $row = $statement->fetch();
    return ($email !== null && (int) $row['by_email'] >= $perEmail) || ($ip !== null && (int) $row['by_ip'] >= $perIp);
}

// Google identities ---------------------------------------------------------------

function find_identity(PDO $pdo, string $provider, string $providerUserId): ?array
{
    $statement = $pdo->prepare('SELECT id, user_id, provider, provider_user_id, email_at_provider FROM app.auth_identities WHERE provider = :p AND provider_user_id = :sub');
    $statement->execute(['p' => $provider, 'sub' => $providerUserId]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function user_has_identity(PDO $pdo, int $userId, string $provider): bool
{
    $statement = $pdo->prepare('SELECT 1 FROM app.auth_identities WHERE user_id = :u AND provider = :p');
    $statement->execute(['u' => $userId, 'p' => $provider]);
    return $statement->fetchColumn() !== false;
}

function insert_identity(PDO $pdo, int $userId, string $provider, string $providerUserId, string $email): void
{
    $pdo->prepare('INSERT INTO app.auth_identities (user_id, provider, provider_user_id, email_at_provider) VALUES (:u, :p, :sub, :e)')
        ->execute(['u' => $userId, 'p' => $provider, 'sub' => $providerUserId, 'e' => $email]);
}

// One-time tokens (reset, verify, invite) -------------------------------------------

function insert_one_time_token(PDO $pdo, int $userId, string $purpose, string $tokenHash, int $minutes): void
{
    $pdo->prepare(<<<'SQL'
        INSERT INTO app.one_time_tokens (user_id, purpose, token_hash, expires_at)
        VALUES (:u, :purpose, :hash, now() + make_interval(mins => :minutes))
    SQL)->execute(['u' => $userId, 'purpose' => $purpose, 'hash' => $tokenHash, 'minutes' => $minutes]);
}

function find_valid_token(PDO $pdo, string $purpose, string $tokenHash): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT t.id, t.user_id, t.purpose, t.expires_at
        FROM app.one_time_tokens t
        WHERE t.purpose = :purpose AND t.token_hash = :hash AND t.used_at IS NULL AND t.expires_at > now()
    SQL);
    $statement->execute(['purpose' => $purpose, 'hash' => $tokenHash]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function consume_token(PDO $pdo, int $tokenId): void
{
    $pdo->prepare('UPDATE app.one_time_tokens SET used_at = now() WHERE id = :id')->execute(['id' => $tokenId]);
}

function expire_tokens_for_user(PDO $pdo, int $userId, string $purpose): void
{
    $pdo->prepare('UPDATE app.one_time_tokens SET used_at = now() WHERE user_id = :u AND purpose = :p AND used_at IS NULL')
        ->execute(['u' => $userId, 'p' => $purpose]);
}

// TOTP -------------------------------------------------------------------------------

function enable_totp(PDO $pdo, int $userId, string $encryptedSecret): void
{
    $pdo->prepare('UPDATE app.users SET totp_secret = :s, totp_enabled_at = now(), totp_last_timestep = NULL WHERE id = :id')
        ->execute(['s' => $encryptedSecret, 'id' => $userId]);
}

function disable_totp(PDO $pdo, int $userId): void
{
    $pdo->prepare('UPDATE app.users SET totp_secret = NULL, totp_enabled_at = NULL, totp_last_timestep = NULL WHERE id = :id')->execute(['id' => $userId]);
    $pdo->prepare('DELETE FROM app.totp_recovery_codes WHERE user_id = :id')->execute(['id' => $userId]);
}

/** Replay guard: store the accepted timestep only if it is newer than the last one. */
function claim_totp_timestep(PDO $pdo, int $userId, int $timestep): bool
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.users SET totp_last_timestep = :ts
        WHERE id = :id AND (totp_last_timestep IS NULL OR totp_last_timestep < :ts)
    SQL);
    $statement->execute(['ts' => $timestep, 'id' => $userId]);
    return $statement->rowCount() === 1;
}

function replace_recovery_codes(PDO $pdo, int $userId, array $codeHashes): void
{
    $pdo->prepare('DELETE FROM app.totp_recovery_codes WHERE user_id = :id')->execute(['id' => $userId]);
    $insert = $pdo->prepare('INSERT INTO app.totp_recovery_codes (user_id, code_hash) VALUES (:id, :hash)');
    foreach ($codeHashes as $hash) {
        $insert->execute(['id' => $userId, 'hash' => $hash]);
    }
}

function unused_recovery_codes(PDO $pdo, int $userId): array
{
    $statement = $pdo->prepare('SELECT id, code_hash FROM app.totp_recovery_codes WHERE user_id = :id AND used_at IS NULL');
    $statement->execute(['id' => $userId]);
    return $statement->fetchAll();
}

function mark_recovery_code_used(PDO $pdo, int $codeId): void
{
    $pdo->prepare('UPDATE app.totp_recovery_codes SET used_at = now() WHERE id = :id')->execute(['id' => $codeId]);
}
