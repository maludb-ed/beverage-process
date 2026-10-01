<?php
declare(strict_types=1);

const ACTIVITY_COLUMNS = 'a.id, a.occurred_at, a.actor_id, a.actor_label, u.display_name AS actor_name, a.source, a.action, a.screen, a.entity_type, a.entity_id, a.entity_label, a.details';

function recent_activity(PDO $pdo, int $limit = 10, bool $excludeScreenEntries = true): array
{
    $where = $excludeScreenEntries ? "WHERE a.action <> 'screen_entered'" : '';
    $statement = $pdo->prepare('SELECT ' . ACTIVITY_COLUMNS . ' FROM app.activity_log a LEFT JOIN app.users u ON u.id = a.actor_id ' . $where . ' ORDER BY a.id DESC LIMIT :limit');
    $statement->bindValue('limit', max(1, min($limit, 100)), PDO::PARAM_INT);
    $statement->execute();
    return array_map('activity_decode_row', $statement->fetchAll());
}

function find_activity(PDO $pdo, string $search, int $page, int $pageSize = 25): array
{
    $offset = max(0, ($page - 1) * $pageSize);
    $sql = 'SELECT ' . ACTIVITY_COLUMNS . ' FROM app.activity_log a LEFT JOIN app.users u ON u.id = a.actor_id';
    $params = [];
    if ($search !== '') {
        $sql .= ' WHERE a.action ILIKE :s OR a.actor_label ILIKE :s OR a.entity_label ILIKE :s OR a.screen ILIKE :s OR a.entity_type ILIKE :s';
        $params['s'] = '%' . $search . '%';
    }
    $sql .= ' ORDER BY a.id DESC LIMIT :limit OFFSET :offset';
    $statement = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $statement->bindValue($key, $value);
    }
    $statement->bindValue('limit', $pageSize + 1, PDO::PARAM_INT);
    $statement->bindValue('offset', $offset, PDO::PARAM_INT);
    $statement->execute();
    $rows = array_map('activity_decode_row', $statement->fetchAll());
    $hasMore = count($rows) > $pageSize;
    return [array_slice($rows, 0, $pageSize), $hasMore];
}

function assistant_transcript(PDO $pdo, int $userId, int $limit = 20): array
{
    $statement = $pdo->prepare('SELECT ' . ACTIVITY_COLUMNS . " FROM app.activity_log a LEFT JOIN app.users u ON u.id = a.actor_id WHERE a.actor_id = :uid AND a.action IN ('assistant_message','ama_question') ORDER BY a.id DESC LIMIT :limit");
    $statement->bindValue('uid', $userId, PDO::PARAM_INT);
    $statement->bindValue('limit', $limit, PDO::PARAM_INT);
    $statement->execute();
    return array_map('activity_decode_row', $statement->fetchAll());
}

function activity_decode_row(array $row): array
{
    $row['details'] = is_string($row['details']) ? (json_decode($row['details'], true) ?: []) : ($row['details'] ?? []);
    return $row;
}
