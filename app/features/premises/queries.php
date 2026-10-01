<?php
declare(strict_types=1);

const PREMISES_KINDS = ['bonded_winery' => 'Bonded winery', 'brewery' => 'Brewery'];
const PREMISES_FORMS = ['5120.17' => '5120.17 (wine premises)', '5130.9' => '5130.9 (brewer, monthly)', '5130.26' => '5130.26 (brewer, quarterly)'];
const PREMISES_FREQUENCIES = ['monthly' => 'Monthly', 'quarterly' => 'Quarterly', 'annual' => 'Annual'];
const PREMISES_TAX_POINTS = ['removal' => 'On removal from bond', 'packaging' => 'At packaging', 'designated_tank' => 'Designated serving tank'];
const PREMISES_CBMA_TIERS = ['none' => 'None', 'tier1' => 'Tier 1', 'tier2' => 'Tier 2', 'tier3' => 'Tier 3'];
const PREMISES_SORTS = ['name' => 'p.name', 'kind' => 'p.kind', 'created_at' => 'p.created_at'];

const PREMISES_COLUMNS = 'p.id, p.name, p.kind, p.registry_number, p.report_form, p.filing_frequency, p.tax_determination_point, p.cbma_tier, p.active, p.created_at, p.updated_at';

function find_premises_list(PDO $pdo, string $search = '', string $sort = 'name', int $page = 1): array
{
    $where = '';
    $params = [];
    if ($search !== '') {
        $where = ' WHERE p.name ILIKE :s OR p.registry_number ILIKE :s';
        $params['s'] = '%' . $search . '%';
    }
    return paged_query(
        $pdo,
        'SELECT ' . PREMISES_COLUMNS . ' FROM app.premises p' . $where . ' ORDER BY ' . order_by($sort, PREMISES_SORTS, 'name'),
        'SELECT count(*) FROM app.premises p' . $where,
        $params,
        $page
    );
}

function find_premises(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT ' . PREMISES_COLUMNS . ' FROM app.premises p WHERE p.id = :id');
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** Active premises as id => name, for selects across the application. */
function premises_options(PDO $pdo, ?string $kind = null): array
{
    $sql = 'SELECT id, name FROM app.premises WHERE active' . ($kind !== null ? ' AND kind = :kind' : '') . ' ORDER BY name';
    $statement = $pdo->prepare($sql);
    $statement->execute($kind !== null ? ['kind' => $kind] : []);
    return array_column($statement->fetchAll(), 'name', 'id');
}

function insert_premises(PDO $pdo, string $name, string $kind, ?string $registryNumber, string $reportForm, string $filingFrequency, string $taxDeterminationPoint, string $cbmaTier, bool $active): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.premises (name, kind, registry_number, report_form, filing_frequency, tax_determination_point, cbma_tier, active)
        VALUES (:name, :kind, :registry_number, :report_form, :filing_frequency, :tax_determination_point, :cbma_tier, :active)
        RETURNING id, name, kind, registry_number, report_form, filing_frequency, tax_determination_point, cbma_tier, active
    SQL);
    $statement->execute(compact('name', 'kind') + [
        'registry_number' => $registryNumber, 'report_form' => $reportForm, 'filing_frequency' => $filingFrequency,
        'tax_determination_point' => $taxDeterminationPoint, 'cbma_tier' => $cbmaTier, 'active' => $active ? 't' : 'f',
    ]);
    return $statement->fetch();
}

function update_premises(PDO $pdo, int $id, string $name, string $kind, ?string $registryNumber, string $reportForm, string $filingFrequency, string $taxDeterminationPoint, string $cbmaTier, bool $active): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.premises
        SET name = :name, kind = :kind, registry_number = :registry_number, report_form = :report_form, filing_frequency = :filing_frequency,
            tax_determination_point = :tax_determination_point, cbma_tier = :cbma_tier, active = :active
        WHERE id = :id
        RETURNING id, name, kind, registry_number, report_form, filing_frequency, tax_determination_point, cbma_tier, active
    SQL);
    $statement->execute(compact('id', 'name', 'kind') + [
        'registry_number' => $registryNumber, 'report_form' => $reportForm, 'filing_frequency' => $filingFrequency,
        'tax_determination_point' => $taxDeterminationPoint, 'cbma_tier' => $cbmaTier, 'active' => $active ? 't' : 'f',
    ]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Premises not found.');
    }
    return $row;
}
