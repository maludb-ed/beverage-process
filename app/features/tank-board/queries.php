<?php
declare(strict_types=1);

const TANK_BOARD_KINDS = ['tank' => 'Tank', 'fermenter' => 'Fermenter', 'brite' => 'Brite', 'tote' => 'Tote', 'ibc' => 'IBC', 'barrel' => 'Barrel', 'press' => 'Press'];

/** What is in every active vessel right now (app.v_vessel_board), by kind then name. */
function find_tank_board(PDO $pdo, ?int $premisesId, ?string $kind): array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT vb.*, st.name AS stage_name
        FROM app.v_vessel_board vb LEFT JOIN app.stages st ON st.code = vb.current_stage_code
        WHERE (:p::bigint IS NULL OR vb.premises_id = :p::bigint) AND (:k::text IS NULL OR vb.vessel_kind = :k::text)
        ORDER BY vb.vessel_kind, vb.vessel_name
    SQL);
    $statement->execute(['p' => $premisesId, 'k' => $kind]);
    return $statement->fetchAll();
}
