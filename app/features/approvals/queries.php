<?php
declare(strict_types=1);

const APPROVAL_KINDS = ['formula' => 'Formula', 'label' => 'Label'];
const APPROVAL_STATUSES = ['not_required' => 'Not required', 'required' => 'Required', 'submitted' => 'Submitted', 'approved' => 'Approved', 'expired' => 'Expired', 'rejected' => 'Rejected'];
const APPROVAL_SORTS = ['product_name' => 'p.name', 'expires_on' => 'pa.expires_on', 'status' => 'pa.status'];
// Status vocabulary for approvals (build spec): approved=success, submitted/required=warning, rejected/expired=danger, not_required=secondary.
const APPROVAL_STATUS_COLORS = ['approved' => 'success', 'submitted' => 'warning', 'required' => 'warning', 'rejected' => 'danger', 'expired' => 'danger', 'not_required' => 'secondary'];

function approval_status_color(string $status): string
{
    return APPROVAL_STATUS_COLORS[$status] ?? 'secondary';
}

function approval_status_badge(string $status, ?string $id = null): string
{
    return badge(humanize($status), approval_status_color($status), $id);
}

function find_approvals(PDO $pdo, string $search = '', array $filters = [], string $sort = 'product_name', int $page = 1): array
{
    $where = [];
    $params = [];
    if ($search !== '') {
        $where[] = '(p.name ILIKE :s OR pa.reference_no ILIKE :s)';
        $params['s'] = '%' . $search . '%';
    }
    if (!empty($filters['kind'])) { $where[] = 'pa.kind = :kind'; $params['kind'] = $filters['kind']; }
    if (!empty($filters['status'])) { $where[] = 'pa.status = :status'; $params['status'] = $filters['status']; }
    if (!empty($filters['product_id'])) { $where[] = 'pa.product_id = :product_id'; $params['product_id'] = (int) $filters['product_id']; }
    $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
    $from = ' FROM app.product_approvals pa JOIN app.products p ON p.id = pa.product_id LEFT JOIN app.packaging_configurations pc ON pc.id = pa.packaging_configuration_id';
    return paged_query(
        $pdo,
        'SELECT pa.id, pa.product_id, p.name AS product_name, p.code AS product_code, pa.kind, pa.packaging_configuration_id, pc.name AS package_name, pa.reference_no, pa.status,
                pa.approved_on, pa.expires_on, (pa.expires_on IS NOT NULL AND pa.expires_on < current_date + 60) AS expiring_soon'
            . $from . $whereSql . ' ORDER BY ' . order_by($sort, APPROVAL_SORTS, 'product_name') . ', pa.id',
        'SELECT count(*)' . $from . $whereSql,
        $params,
        $page
    );
}

function find_approval(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT pa.id, pa.product_id, p.name AS product_name, p.code AS product_code, pa.kind, pa.packaging_configuration_id, pa.reference_no, pa.status,
               pa.approved_on, pa.expires_on, pa.attachment_id, pa.notes, at.file_name AS attachment_name
        FROM app.product_approvals pa JOIN app.products p ON p.id = pa.product_id LEFT JOIN app.attachments at ON at.id = pa.attachment_id
        WHERE pa.id = :id
    SQL);
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** Packaging configurations of a product as id => name. */
function approval_package_options(PDO $pdo, int $productId): array
{
    $statement = $pdo->prepare('SELECT id, name FROM app.packaging_configurations WHERE product_id = :p ORDER BY name');
    $statement->execute(['p' => $productId]);
    return array_column($statement->fetchAll(), 'name', 'id');
}

function insert_approval(PDO $pdo, int $productId, string $kind, ?int $packagingConfigurationId, ?string $referenceNo, string $status, ?string $approvedOn, ?string $expiresOn, ?int $attachmentId, ?string $notes): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.product_approvals (product_id, kind, packaging_configuration_id, reference_no, status, approved_on, expires_on, attachment_id, notes)
        VALUES (:product_id, :kind, :pc, :reference_no, :status, :approved_on, :expires_on, :attachment_id, :notes)
        RETURNING id, product_id, kind, packaging_configuration_id, reference_no, status, approved_on, expires_on, attachment_id, notes
    SQL);
    $statement->execute(['product_id' => $productId, 'kind' => $kind, 'pc' => $packagingConfigurationId, 'reference_no' => $referenceNo, 'status' => $status,
        'approved_on' => $approvedOn, 'expires_on' => $expiresOn, 'attachment_id' => $attachmentId, 'notes' => $notes]);
    return $statement->fetch();
}

/** Updates an approval; $attachmentId null keeps the existing attachment. */
function update_approval(PDO $pdo, int $id, int $productId, string $kind, ?int $packagingConfigurationId, ?string $referenceNo, string $status, ?string $approvedOn, ?string $expiresOn, ?int $attachmentId, ?string $notes): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.product_approvals SET product_id = :product_id, kind = :kind, packaging_configuration_id = :pc, reference_no = :reference_no, status = :status,
               approved_on = :approved_on, expires_on = :expires_on, attachment_id = COALESCE(:attachment_id, attachment_id), notes = :notes
        WHERE id = :id
        RETURNING id, product_id, kind, packaging_configuration_id, reference_no, status, approved_on, expires_on, attachment_id, notes
    SQL);
    $statement->execute(['id' => $id, 'product_id' => $productId, 'kind' => $kind, 'pc' => $packagingConfigurationId, 'reference_no' => $referenceNo, 'status' => $status,
        'approved_on' => $approvedOn, 'expires_on' => $expiresOn, 'attachment_id' => $attachmentId, 'notes' => $notes]);
    return $statement->fetch();
}

/** Stores an attachment row for an approval (entity_type product_approval) and links it. */
function insert_approval_attachment(PDO $pdo, int $approvalId, string $kind, string $fileName, string $mimeType, string $storagePath, int $byteSize, int $uploadedBy): int
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.attachments (entity_type, entity_id, kind, file_name, mime_type, storage_path, byte_size, uploaded_by)
        VALUES ('product_approval', :eid, :kind, :fn, :mime, :path, :size, :by) RETURNING id
    SQL);
    $statement->execute(['eid' => $approvalId, 'kind' => $kind, 'fn' => $fileName, 'mime' => $mimeType, 'path' => $storagePath, 'size' => $byteSize, 'by' => $uploadedBy]);
    $attachmentId = (int) $statement->fetchColumn();
    $pdo->prepare('UPDATE app.product_approvals SET attachment_id = :a WHERE id = :id')->execute(['a' => $attachmentId, 'id' => $approvalId]);
    return $attachmentId;
}

/** Active products with no approved or not_required row for a kind: [product_id, product_code, product_name, kind]. */
function find_products_missing_approvals(PDO $pdo): array
{
    return $pdo->query(<<<'SQL'
        SELECT p.id AS product_id, p.code AS product_code, p.name AS product_name, k.kind
        FROM app.products p CROSS JOIN (VALUES ('formula'), ('label')) AS k(kind)
        WHERE p.status = 'active'
          AND NOT EXISTS (SELECT 1 FROM app.product_approvals pa WHERE pa.product_id = p.id AND pa.kind = k.kind AND pa.status IN ('approved', 'not_required'))
        ORDER BY p.name, k.kind
    SQL)->fetchAll();
}
