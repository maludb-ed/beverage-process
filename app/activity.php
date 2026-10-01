<?php
declare(strict_types=1);

// The activity log: every screen render and every state change writes a row
// through app.log_activity(). Rows are shipped into MaluDB by the ingest timer.

function actor_label(): string
{
    $user = current_user();
    if ($user !== null) {
        return 'user/' . $user['id'] . ' ' . $user['display_name'];
    }
    return 'anonymous';
}

function session_hash(): ?string
{
    $id = session_id();
    return $id === '' ? null : hash('sha256', $id);
}

/**
 * Write one activity row. Call inside the same transaction as the write it
 * describes. $source is 'screen' unless the request came from the command bar
 * ('command_bar'), the AMA page ('ama'), or a system job ('system').
 */
function log_activity(
    PDO $pdo,
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    ?string $entityLabel = null,
    ?array $before = null,
    ?array $after = null,
    array $details = [],
    ?string $screen = null,
    string $source = 'screen',
    ?int $actorId = null,
    ?string $actorLabel = null
): int {
    $user = current_user();
    $statement = $pdo->prepare(
        'SELECT app.log_activity(:actor_id, :actor_label, :source, :session_hash, :request_id, :action, :screen,
                                 :entity_type, :entity_id, :entity_label, :before, :after, :details, :ip)'
    );
    $statement->execute([
        'actor_id'     => $actorId ?? ($user['id'] ?? null),
        'actor_label'  => $actorLabel ?? actor_label(),
        'source'       => $source === 'screen' && function_exists('is_action_token_request') && is_action_token_request() ? 'command_bar' : $source,
        'session_hash' => session_hash(),
        'request_id'   => request_id(),
        'action'       => $action,
        'screen'       => $screen ?? ($GLOBALS['__current_screen'] ?? null),
        'entity_type'  => $entityType,
        'entity_id'    => $entityId,
        'entity_label' => $entityLabel,
        'before'       => $before === null ? null : json_encode($before, JSON_THROW_ON_ERROR),
        'after'        => $after === null ? null : json_encode($after, JSON_THROW_ON_ERROR),
        'details'      => json_encode($details, JSON_THROW_ON_ERROR),
        'ip'           => client_ip(),
    ]);
    return (int) $statement->fetchColumn();
}

/** Every screen render logs its entry; also remembers the screen for later log rows in this request. */
function log_screen_entered(string $screen, ?string $entityType = null, ?int $entityId = null, ?string $entityLabel = null): void
{
    $GLOBALS['__current_screen'] = $screen;
    try {
        log_activity(db(), 'screen_entered', $entityType, $entityId, $entityLabel, null, null, [], $screen);
    } catch (Throwable $exception) {
        error_log('screen_entered log failed: ' . $exception->getMessage());
    }
}
