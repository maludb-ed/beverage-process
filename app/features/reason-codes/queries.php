<?php
declare(strict_types=1);

const REASON_APPLIES_TO = ['adjustment' => 'Adjustment', 'loss' => 'Loss', 'override' => 'Override', 'count' => 'Count', 'short_close' => 'Short close', 'dump' => 'Dump'];
const REASON_TTB_CATEGORIES = ['none' => 'None', 'inventory_loss' => 'Inventory loss', 'casualty_loss' => 'Casualty loss', 'testing' => 'Testing', 'destroyed' => 'Destroyed', 'breakage' => 'Breakage', 'shortage' => 'Shortage'];
const REASON_CLASSIFICATIONS = ['expected' => 'Expected', 'exceptional' => 'Exceptional'];
const REASON_SORTS = ['code' => 'r.code', 'applies_to' => 'r.applies_to', 'ttb_category' => 'r.ttb_category'];
const REASON_COLUMNS = 'r.id, r.code, r.name, r.applies_to, r.ttb_category, r.classification, r.requires_approval_above, r.active';

/** loss and dump thresholds are stored in liters; adjustment and count in the item's base unit. */
function reason_code_threshold_is_volume(string $appliesTo): bool
{
    return in_array($appliesTo, ['loss', 'dump'], true);
}

function find_reason_codes(PDO $pdo, string $search = '', string $sort = 'code', int $page = 1, ?string $appliesTo = null): array
{
    $clauses = [];
    $params = [];
    if ($search !== '') {
        $clauses[] = '(r.code ILIKE :s OR r.name ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    if ($appliesTo !== null && $appliesTo !== '') {
        $clauses[] = 'r.applies_to = :applies_to';
        $params['applies_to'] = $appliesTo;
    }
    $where = $clauses === [] ? '' : ' WHERE ' . implode(' AND ', $clauses);
    return paged_query(
        $pdo,
        'SELECT ' . REASON_COLUMNS . ' FROM app.reason_codes r' . $where . ' ORDER BY ' . order_by($sort, REASON_SORTS, 'code'),
        'SELECT count(*) FROM app.reason_codes r' . $where,
        $params,
        $page
    );
}

function find_reason_code(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT ' . REASON_COLUMNS . ' FROM app.reason_codes r WHERE r.id = :id');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

function insert_reason_code(PDO $pdo, string $code, string $name, string $appliesTo, string $ttbCategory, string $classification, ?float $requiresApprovalAbove, bool $active): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.reason_codes (code, name, applies_to, ttb_category, classification, requires_approval_above, active)
        VALUES (:code, :name, :applies_to, :ttb_category, :classification, :above, :active)
        RETURNING id, code, name, applies_to, ttb_category, classification, requires_approval_above, active
    SQL);
    $statement->execute(['code' => $code, 'name' => $name, 'applies_to' => $appliesTo, 'ttb_category' => $ttbCategory,
        'classification' => $classification, 'above' => $requiresApprovalAbove, 'active' => $active ? 't' : 'f']);
    return $statement->fetch();
}

function update_reason_code(PDO $pdo, int $id, string $code, string $name, string $appliesTo, string $ttbCategory, string $classification, ?float $requiresApprovalAbove, bool $active): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.reason_codes
        SET code = :code, name = :name, applies_to = :applies_to, ttb_category = :ttb_category, classification = :classification,
            requires_approval_above = :above, active = :active
        WHERE id = :id
        RETURNING id, code, name, applies_to, ttb_category, classification, requires_approval_above, active
    SQL);
    $statement->execute(['id' => $id, 'code' => $code, 'name' => $name, 'applies_to' => $appliesTo, 'ttb_category' => $ttbCategory,
        'classification' => $classification, 'above' => $requiresApprovalAbove, 'active' => $active ? 't' : 'f']);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Reason code not found.');
    }
    return $row;
}
