<?php
declare(strict_types=1);

function find_client_settings(PDO $pdo): array
{
    $row = $pdo->query("SELECT id, client_name, subdomain, timezone, volume_display_unit, mass_display_unit, fruit_display_unit, (settings #>> '{equipment,double_booking}') = 'allow' AS equipment_double_booking FROM app.client_settings WHERE id = 1")->fetch();
    if ($row === false) {
        throw new RuntimeException('Client settings row is missing.');
    }
    return $row;
}

/** Units as code => "code (name)" for one dimension; $nonBaseOnly drops the metric base unit. */
function settings_unit_options(PDO $pdo, string $dimension, bool $nonBaseOnly = false): array
{
    $statement = $pdo->prepare('SELECT code, name FROM app.units WHERE dimension = :d' . ($nonBaseOnly ? ' AND NOT is_base' : '') . ' ORDER BY display_order');
    $statement->execute(['d' => $dimension]);
    $options = [];
    foreach ($statement->fetchAll() as $row) {
        $options[$row['code']] = $row['code'] . ' (' . $row['name'] . ')';
    }
    return $options;
}

function update_client_settings(PDO $pdo, string $clientName, string $timezone, string $volumeDisplayUnit, string $massDisplayUnit, string $fruitDisplayUnit): array
{
    $statement = $pdo->prepare(<<<'SQL'
        UPDATE app.client_settings
        SET client_name = :client_name, timezone = :timezone, volume_display_unit = :volume, mass_display_unit = :mass, fruit_display_unit = :fruit
        WHERE id = 1
        RETURNING id, client_name, subdomain, timezone, volume_display_unit, mass_display_unit, fruit_display_unit
    SQL);
    $statement->execute(['client_name' => $clientName, 'timezone' => $timezone, 'volume' => $volumeDisplayUnit, 'mass' => $massDisplayUnit, 'fruit' => $fruitDisplayUnit]);
    return $statement->fetch();
}
