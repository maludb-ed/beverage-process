<?php
declare(strict_types=1);

const SENSORY_SORTS = ['panel_on' => 's.panel_on', 'verdict' => 's.verdict'];
const SENSORY_PAGE_SIZE = 25;
const SENSORY_VERDICTS = ['pass' => 'Pass', 'hold' => 'Hold', 'fail' => 'Fail'];
const SENSORY_ATTRIBUTES = ['aroma' => 'Aroma', 'acidity' => 'Acidity', 'sweetness' => 'Sweetness', 'tannin' => 'Tannin', 'body' => 'Body'];
const SENSORY_FAULTS = ['acetic' => 'Acetic', 'ethyl_acetate' => 'Ethyl acetate', 'sulfide' => 'Sulfide', 'oxidation' => 'Oxidation', 'mousy' => 'Mousy', 'brett' => 'Brett', 'diacetyl' => 'Diacetyl'];

const SENSORY_FROM = <<<'SQL'
    FROM app.sensory_records s
    LEFT JOIN app.users u ON u.id = s.panelist_id
    LEFT JOIN app.batches b ON s.target_kind = 'batch' AND b.id = s.target_id
    LEFT JOIN app.products bp ON bp.id = b.product_id
    LEFT JOIN app.lots l ON s.target_kind = 'lot' AND l.id = s.target_id
    LEFT JOIN app.items li ON li.id = l.item_id
SQL;
const SENSORY_COLUMNS = <<<'SQL'
    s.id, s.target_kind, s.target_id, s.panel_on, s.panelist_id, s.panelist_name, s.sample_code, s.verdict, s.attributes, s.faults, s.comment, s.created_at,
    COALESCE(u.display_name, s.panelist_name) AS panelist_label, COALESCE(b.number, l.lot_number) AS target_number, COALESCE(bp.name, li.name) AS target_product,
    (s.created_at::date = current_date) AS is_today
SQL;

function find_sensory_records(PDO $pdo, string $search = '', string $sort = '-panel_on', int $page = 1): array
{
    $where = '';
    $params = [];
    if ($search !== '') {
        $where = ' WHERE (b.number ILIKE :s OR l.lot_number ILIKE :s OR s.panelist_name ILIKE :s OR u.display_name ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    return paged_query($pdo, 'SELECT ' . SENSORY_COLUMNS . SENSORY_FROM . $where . ' ORDER BY ' . order_by($sort, SENSORY_SORTS, '-panel_on') . ', s.id DESC',
        'SELECT count(*) ' . SENSORY_FROM . $where, $params, $page, SENSORY_PAGE_SIZE);
}

function find_sensory_record(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT ' . SENSORY_COLUMNS . SENSORY_FROM . ' WHERE s.id = :id');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function insert_sensory_record(PDO $pdo, string $targetKind, int $targetId, string $panelOn, ?int $panelistId, ?string $panelistName, ?string $sampleCode, string $verdict, array $attributes, array $faults, ?string $comment): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.sensory_records (target_kind, target_id, panel_on, panelist_id, panelist_name, sample_code, verdict, attributes, faults, comment)
        VALUES (:kind, :id, :on, :pid, :pname, :sample, :verdict, :attrs, :faults, :comment)
        RETURNING id, verdict
    SQL);
    $statement->execute(['kind' => $targetKind, 'id' => $targetId, 'on' => $panelOn, 'pid' => $panelistId, 'pname' => $panelistName, 'sample' => $sampleCode, 'verdict' => $verdict,
        'attrs' => json_encode((object) $attributes, JSON_THROW_ON_ERROR), 'faults' => json_encode(array_values($faults), JSON_THROW_ON_ERROR), 'comment' => $comment]);
    return $statement->fetch();
}

/** Own record (panelist_id = actor), recorded today. */
function delete_sensory_record(PDO $pdo, int $id, int $actorId): bool
{
    $statement = $pdo->prepare('DELETE FROM app.sensory_records WHERE id = :id AND panelist_id = :actor AND created_at::date = current_date');
    $statement->execute(['id' => $id, 'actor' => $actorId]);
    return $statement->rowCount() === 1;
}
