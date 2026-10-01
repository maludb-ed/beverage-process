<?php
declare(strict_types=1);

function find_units(PDO $pdo, ?string $dimension = null): array
{
    $statement = $pdo->prepare('SELECT code, name, dimension, to_base_factor, is_base, display_order FROM app.units' . ($dimension !== null ? ' WHERE dimension = :dimension' : '') . ' ORDER BY display_order, code');
    $statement->execute($dimension !== null ? ['dimension' => $dimension] : []);
    return $statement->fetchAll();
}
