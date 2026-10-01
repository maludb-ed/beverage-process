<?php
declare(strict_types=1);

/** Specs of one product, ordered by stage then measurement. */
function find_specs(PDO $pdo, int $productId): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT s.id, s.product_id, s.stage_code, st.name AS stage_name, s.measurement_type_code, mt.name AS measurement_name, mt.unit AS measurement_unit,
               mt.decimals AS measurement_decimals, s.min_value, s.max_value, s.target_value, s.active
        FROM app.specs s
        JOIN app.stages st ON st.code = s.stage_code
        JOIN app.measurement_types mt ON mt.code = s.measurement_type_code
        WHERE s.product_id = :id
        ORDER BY st.display_order, mt.name
    SQL);
    $statement->execute(['id' => $productId]);
    return $statement->fetchAll();
}

function find_spec(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT s.id, s.product_id, p.name AS product_name, p.beverage_type, s.stage_code, s.measurement_type_code, s.min_value, s.max_value, s.target_value, s.active
        FROM app.specs s JOIN app.products p ON p.id = s.product_id WHERE s.id = :id
    SQL);
    $statement->execute(['id' => $id]);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** Measurement types as code => "Name (unit)". */
function spec_measurement_options(PDO $pdo): array
{
    $rows = $pdo->query("SELECT code, name || ' (' || unit || ')' AS label FROM app.measurement_types ORDER BY name")->fetchAll();
    return array_column($rows, 'label', 'code');
}

function insert_spec(PDO $pdo, int $productId, string $stageCode, string $measurementTypeCode, ?float $minValue, ?float $maxValue, ?float $targetValue, bool $active): array
{
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.specs (product_id, stage_code, measurement_type_code, min_value, max_value, target_value, active)
        VALUES (:product_id, :stage, :measurement, :min, :max, :target, :active)
        RETURNING id, product_id, stage_code, measurement_type_code, min_value, max_value, target_value, active
    SQL);
    $statement->execute(['product_id' => $productId, 'stage' => $stageCode, 'measurement' => $measurementTypeCode, 'min' => $minValue, 'max' => $maxValue, 'target' => $targetValue, 'active' => $active ? 't' : 'f']);
    return $statement->fetch();
}

function update_spec(PDO $pdo, int $id, int $productId, string $stageCode, string $measurementTypeCode, ?float $minValue, ?float $maxValue, ?float $targetValue, bool $active): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.specs SET product_id = :product_id, stage_code = :stage, measurement_type_code = :measurement, min_value = :min, max_value = :max, target_value = :target, active = :active
        WHERE id = :id
        RETURNING id, product_id, stage_code, measurement_type_code, min_value, max_value, target_value, active
    SQL);
    $statement->execute(['id' => $id, 'product_id' => $productId, 'stage' => $stageCode, 'measurement' => $measurementTypeCode, 'min' => $minValue, 'max' => $maxValue, 'target' => $targetValue, 'active' => $active ? 't' : 'f']);
    return $statement->fetch();
}

function spec_is_referenced(PDO $pdo, int $id): bool
{
    $statement = $pdo->prepare('SELECT EXISTS (SELECT 1 FROM app.readings WHERE spec_id = :id)');
    $statement->execute(['id' => $id]);
    return (bool) $statement->fetchColumn();
}

/** Deletes the spec when no reading references it (true); otherwise deactivates it (false). */
function delete_spec(PDO $pdo, int $id): bool
{
    if (spec_is_referenced($pdo, $id)) {
        $pdo->prepare('UPDATE app.specs SET active = false WHERE id = :id')->execute(['id' => $id]);
        return false;
    }
    $pdo->prepare('DELETE FROM app.specs WHERE id = :id')->execute(['id' => $id]);
    return true;
}
