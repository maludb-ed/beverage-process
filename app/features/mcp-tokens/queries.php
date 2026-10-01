<?php
declare(strict_types=1);

// AI access tokens (screen settings-mcp-tokens): bearer tokens for the client-facing
// MCP servers. Only the sha256 hash and an 8-character prefix are stored; the full
// token is shown once, right after it is created.

const MCP_TOKEN_SCOPES = ['records' => 'Records (what is true now)', 'activity' => 'Activity (who did what, when)'];
const MCP_TOKEN_SORTS = ['name' => 't.name', 'scope' => 't.scope', 'created_at' => 't.created_at', 'last_used_at' => 't.last_used_at'];
/** Tokens the built-in assistant service uses (config/services.env); shown, never revocable here. */
const MCP_TOKEN_SERVICE_NAME = 'assistant-service';

const MCP_TOKEN_COLUMNS = 't.id, t.name, t.scope, t.token_prefix, t.created_by, cu.display_name AS created_by_name, t.created_at,
    t.last_used_at, t.revoked_at, t.revoked_by, ru.display_name AS revoked_by_name';
const MCP_TOKEN_FROM = ' FROM app.mcp_access_tokens t LEFT JOIN app.users cu ON cu.id = t.created_by LEFT JOIN app.users ru ON ru.id = t.revoked_by';

/** Active tokens first, then revoked; the service tokens are included and flagged by name. */
function find_mcp_tokens_list(PDO $pdo, string $sort = '-created_at', int $page = 1): array
{
    return paged_query(
        $pdo,
        'SELECT ' . MCP_TOKEN_COLUMNS . MCP_TOKEN_FROM . ' ORDER BY (t.revoked_at IS NOT NULL), ' . order_by($sort, MCP_TOKEN_SORTS, '-created_at') . ', t.id DESC',
        'SELECT count(*) FROM app.mcp_access_tokens t',
        [],
        $page
    );
}

function find_mcp_token(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT ' . MCP_TOKEN_COLUMNS . MCP_TOKEN_FROM . ' WHERE t.id = :id');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function mcp_token_name_in_use(PDO $pdo, string $name): bool
{
    $statement = $pdo->prepare('SELECT 1 FROM app.mcp_access_tokens WHERE lower(name) = lower(:name) AND revoked_at IS NULL');
    $statement->execute(['name' => $name]);
    return $statement->fetchColumn() !== false;
}

function mcp_token_is_service(array $token): bool
{
    return $token['name'] === MCP_TOKEN_SERVICE_NAME;
}

/** Stores the hash and prefix of $token; returns the stored row (never the token). */
function insert_mcp_token(PDO $pdo, string $name, string $scope, string $token, int $createdBy): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.mcp_access_tokens (name, scope, token_prefix, token_hash, created_by)
        VALUES (:name, :scope, :prefix, :hash, :created_by)
        RETURNING id, name, scope, token_prefix, created_at
    SQL);
    $statement->execute(['name' => $name, 'scope' => $scope, 'prefix' => substr($token, 0, 8), 'hash' => hash('sha256', $token), 'created_by' => $createdBy]);
    return $statement->fetch();
}

function revoke_mcp_token(PDO $pdo, int $id, int $userId): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.mcp_access_tokens SET revoked_at = now(), revoked_by = :user_id
        WHERE id = :id AND revoked_at IS NULL
        RETURNING id, name, scope, token_prefix, revoked_at
    SQL);
    $statement->execute(['id' => $id, 'user_id' => $userId]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** The public MCP endpoint for a scope, from config('app.base_url'). */
function mcp_token_endpoint(string $baseUrl, string $scope): string
{
    return rtrim($baseUrl, '/') . '/mcp/' . $scope;
}
