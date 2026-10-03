<?php
declare(strict_types=1);

const RECIPE_PURPOSES = [
    'base_juice' => 'Base juice', 'yeast' => 'Yeast', 'nutrient' => 'Nutrient', 'sulfite' => 'Sulfite', 'enzyme' => 'Enzyme',
    'sweetener' => 'Sweetener', 'acid' => 'Acid', 'fining' => 'Fining', 'other' => 'Other',
];
const RECIPE_BASES = ['per_batch' => 'Fixed per batch', 'per_volume' => 'Per volume'];
const RECIPE_MODES = ['explicit' => 'Explicit', 'backflush' => 'Backflush'];
const RECIPE_BEVERAGE_PREMISES = ['cider' => 'bonded_winery', 'wine' => 'bonded_winery', 'beer' => 'brewery'];

const RECIPE_HEADER_SQL = <<<'SQL'
    SELECT rv.id, rv.product_id, rv.version_no, rv.status, rv.target_batch_volume_l, rv.expected_total_loss_pct, rv.standard_cost_total, rv.standard_cost_per_l,
           rv.change_note, rv.activated_at, rv.activated_by, rv.created_at, rv.updated_at,
           p.code AS product_code, p.name AS product_name, p.beverage_type, p.status AS product_status, ua.display_name AS activated_by_name
    FROM app.recipe_versions rv
    JOIN app.products p ON p.id = rv.product_id
    LEFT JOIN app.users ua ON ua.id = rv.activated_by
SQL;

function find_recipe_versions(PDO $pdo, int $productId): array
{
    $statement = $pdo->prepare(RECIPE_HEADER_SQL . ' WHERE rv.product_id = :id ORDER BY rv.version_no DESC');
    $statement->execute(['id' => $productId]);
    return $statement->fetchAll();
}

/** A version with its stages and lines (item names and units included). */
function find_recipe_version(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare(RECIPE_HEADER_SQL . ' WHERE rv.id = :id');
    $statement->execute(['id' => $id]);
    $version = $statement->fetch();
    if ($version === false) {
        return null;
    }
    $stages = $pdo->prepare('SELECT rs.id, rs.seq, rs.stage_code, st.name AS stage_name, rs.expected_loss_pct, rs.expected_duration_days, rs.instructions
        FROM app.recipe_stages rs JOIN app.stages st ON st.code = rs.stage_code WHERE rs.recipe_version_id = :id ORDER BY rs.seq');
    $stages->execute(['id' => $id]);
    $version['stages'] = $stages->fetchAll();
    $lines = $pdo->prepare(<<<'SQL'
        SELECT rl.id, rl.seq, rl.item_id, rl.stage_code, st.name AS stage_name, rl.purpose, rl.qty_per_batch_base, rl.qty_per_l, rl.consumption_mode, rl.notes,
               i.code AS item_code, i.name AS item_name, i.item_class, i.base_unit_code, i.consumption_mode AS item_consumption_mode
        FROM app.recipe_lines rl JOIN app.items i ON i.id = rl.item_id JOIN app.stages st ON st.code = rl.stage_code
        WHERE rl.recipe_version_id = :id ORDER BY rl.seq
    SQL);
    $lines->execute(['id' => $id]);
    $version['lines'] = $lines->fetchAll();
    return $version;
}

/** Items a recipe may use: id => [code, name, base_unit_code, item_class, consumption_mode]. $includeIds keeps inactive items already on the version. */
function recipe_item_catalog(PDO $pdo, array $includeIds = []): array
{
    $ids = '{' . implode(',', array_map('intval', $includeIds)) . '}';
    $statement = $pdo->prepare('SELECT id, code, name, base_unit_code, item_class, consumption_mode FROM app.items
        WHERE item_class = ANY(:classes::text[]) AND (active OR id = ANY(:ids::bigint[])) ORDER BY code');
    require_once __DIR__ . '/../items/queries.php';
    $statement->execute(['classes' => '{' . implode(',', item_classes_where($pdo, 'recipe_ingredient')) . '}', 'ids' => $ids]);
    $catalog = [];
    foreach ($statement->fetchAll() as $row) {
        $catalog[(int) $row['id']] = $row;
    }
    return $catalog;
}

/** Other versions of a product as id => "vN (status)". */
function recipe_version_options(PDO $pdo, int $productId, ?int $excludeId = null): array
{
    $options = [];
    foreach (find_recipe_versions($pdo, $productId) as $row) {
        if ($excludeId === null || (int) $row['id'] !== $excludeId) {
            $options[(int) $row['id']] = 'v' . $row['version_no'] . ' (' . $row['status'] . ')';
        }
    }
    return $options;
}

/** Creates a draft version (next version_no for the product), optionally copying stages and lines. */
function insert_recipe_version(PDO $pdo, int $productId, float $targetBatchVolumeL, ?int $copyFromId, ?string $changeNote, int $createdBy): array
{
    $pdo->prepare('SELECT id FROM app.products WHERE id = :id FOR UPDATE')->execute(['id' => $productId]);
    $statement = $pdo->prepare(<<<'SQL'
        INSERT INTO app.recipe_versions (product_id, version_no, status, target_batch_volume_l, change_note, created_by)
        VALUES (:product_id, (SELECT COALESCE(max(version_no), 0) + 1 FROM app.recipe_versions WHERE product_id = :product_id), 'draft', :volume, :note, :by)
        RETURNING id, product_id, version_no, status, target_batch_volume_l, change_note
    SQL);
    $statement->execute(['product_id' => $productId, 'volume' => $targetBatchVolumeL, 'note' => $changeNote, 'by' => $createdBy]);
    $version = $statement->fetch();
    $version['copied_from'] = null;
    if ($copyFromId !== null) {
        $pdo->prepare('INSERT INTO app.recipe_stages (recipe_version_id, seq, stage_code, expected_loss_pct, expected_duration_days, instructions)
            SELECT :new, seq, stage_code, expected_loss_pct, expected_duration_days, instructions FROM app.recipe_stages WHERE recipe_version_id = :old ORDER BY seq')
            ->execute(['new' => $version['id'], 'old' => $copyFromId]);
        $pdo->prepare('INSERT INTO app.recipe_lines (recipe_version_id, seq, item_id, stage_code, purpose, qty_per_batch_base, qty_per_l, consumption_mode, notes)
            SELECT :new, seq, item_id, stage_code, purpose, qty_per_batch_base, qty_per_l, consumption_mode, notes FROM app.recipe_lines WHERE recipe_version_id = :old ORDER BY seq')
            ->execute(['new' => $version['id'], 'old' => $copyFromId]);
        $version['copied_from'] = $copyFromId;
    }
    return $version;
}

/** Updates the editable header of a draft version. Throws when the version is not a draft. */
function update_recipe_version(PDO $pdo, int $id, float $targetBatchVolumeL, ?string $changeNote): array
{
    $statement = $pdo->prepare("UPDATE app.recipe_versions SET target_batch_volume_l = :volume, change_note = :note WHERE id = :id AND status = 'draft'
        RETURNING id, product_id, version_no, status, target_batch_volume_l, change_note");
    $statement->execute(['volume' => $targetBatchVolumeL, 'note' => $changeNote, 'id' => $id]);
    $row = $statement->fetch();
    if ($row === false) {
        throw new RuntimeException('Only a draft version can be edited; create a new version.');
    }
    return $row;
}

/** Replaces all stages of a draft version. $stages: validated rows (seq, stage_code, expected_loss_pct, expected_duration_days, instructions). */
function replace_recipe_stages(PDO $pdo, int $versionId, array $stages): void
{
    $pdo->prepare('DELETE FROM app.recipe_stages WHERE recipe_version_id = :id')->execute(['id' => $versionId]);
    $insert = $pdo->prepare('INSERT INTO app.recipe_stages (recipe_version_id, seq, stage_code, expected_loss_pct, expected_duration_days, instructions)
        VALUES (:v, :seq, :stage, :loss, :days, :instructions)');
    foreach ($stages as $stage) {
        $insert->execute(['v' => $versionId, 'seq' => $stage['seq'], 'stage' => $stage['stage_code'], 'loss' => $stage['expected_loss_pct'],
            'days' => $stage['expected_duration_days'], 'instructions' => $stage['instructions']]);
    }
}

/** Replaces all lines of a draft version. $lines: validated rows with qty_per_batch_base xor qty_per_l. */
function replace_recipe_lines(PDO $pdo, int $versionId, array $lines): void
{
    $pdo->prepare('DELETE FROM app.recipe_lines WHERE recipe_version_id = :id')->execute(['id' => $versionId]);
    $insert = $pdo->prepare('INSERT INTO app.recipe_lines (recipe_version_id, seq, item_id, stage_code, purpose, qty_per_batch_base, qty_per_l, consumption_mode, notes)
        VALUES (:v, :seq, :item, :stage, :purpose, :batch, :perl, :mode, :notes)');
    foreach ($lines as $line) {
        $insert->execute(['v' => $versionId, 'seq' => $line['seq'], 'item' => $line['item_id'], 'stage' => $line['stage_code'], 'purpose' => $line['purpose'],
            'batch' => $line['qty_per_batch_base'], 'perl' => $line['qty_per_l'], 'mode' => $line['consumption_mode'], 'notes' => $line['notes']]);
    }
}

/** The current overhead rate for the premises kind matching a beverage; lowest premises id wins. Null when none applies. */
function recipe_overhead_rate(PDO $pdo, string $beverageType): ?array
{
    $statement = $pdo->prepare(<<<'SQL'
        SELECT o.rate_per_l, o.premises_id, pr.name AS premises_name, o.effective_from
        FROM app.overhead_rates o JOIN app.premises pr ON pr.id = o.premises_id
        WHERE pr.active AND pr.kind = :kind AND o.effective_from <= current_date
          AND o.effective_from = (SELECT max(x.effective_from) FROM app.overhead_rates x WHERE x.premises_id = o.premises_id AND x.effective_from <= current_date)
        ORDER BY pr.id LIMIT 1
    SQL);
    $statement->execute(['kind' => RECIPE_BEVERAGE_PREMISES[$beverageType] ?? 'bonded_winery']);
    $row = $statement->fetch();
    return $row === false ? null : $row;
}

/** Activates a draft version: retires the product's active version, snapshots losses and standard cost. Throws RuntimeException on a failed precondition. */
function activate_recipe_version(PDO $pdo, int $id, int $actorId): array
{
    $lock = $pdo->prepare('SELECT status, product_id FROM app.recipe_versions WHERE id = :id FOR UPDATE');
    $lock->execute(['id' => $id]);
    $row = $lock->fetch();
    if ($row === false) {
        throw new RuntimeException('That recipe version does not exist.');
    }
    $version = find_recipe_version($pdo, $id);
    if ($version['status'] !== 'draft') {
        throw new RuntimeException('Version ' . $version['version_no'] . ' is ' . $version['status'] . '; only a draft can be activated.');
    }
    if ($version['stages'] === []) {
        throw new RuntimeException('Add at least one stage before activating.');
    }
    if ($version['lines'] === []) {
        throw new RuntimeException('Add at least one line before activating.');
    }
    $stageCodes = array_column($version['stages'], 'stage_code');
    foreach ($version['lines'] as $line) {
        if (!in_array($line['stage_code'], $stageCodes, true)) {
            throw new RuntimeException('Line ' . $line['seq'] . ' (' . $line['item_code'] . ') uses stage "' . $line['stage_name'] . '", which this version does not have.');
        }
    }
    $keep = 1.0;
    foreach ($version['stages'] as $stage) {
        $keep *= 1 - (float) $stage['expected_loss_pct'] / 100;
    }
    $totalLoss = round(100 * (1 - $keep), 2);

    $costs = $pdo->prepare(<<<'SQL'
        SELECT rl.id, rl.qty_per_batch_base, rl.qty_per_l, i.costing_method, i.standard_cost_per_base,
               (SELECT l.unit_cost_base FROM app.lots l WHERE l.item_id = rl.item_id ORDER BY l.received_on DESC NULLS LAST, l.id DESC LIMIT 1) AS last_lot_cost
        FROM app.recipe_lines rl JOIN app.items i ON i.id = rl.item_id WHERE rl.recipe_version_id = :id
    SQL);
    $costs->execute(['id' => $id]);
    $volumeL = (float) $version['target_batch_volume_l'];
    $materials = 0.0;
    foreach ($costs->fetchAll() as $line) {
        $qty = $line['qty_per_batch_base'] !== null ? (float) $line['qty_per_batch_base'] : (float) $line['qty_per_l'] * $volumeL;
        $unitCost = $line['costing_method'] === 'standard' ? (float) ($line['standard_cost_per_base'] ?? 0) : (float) ($line['last_lot_cost'] ?? 0);
        $materials += $qty * $unitCost;
    }
    $overheadRate = recipe_overhead_rate($pdo, $version['beverage_type']);
    $overhead = $overheadRate === null ? 0.0 : $volumeL * (float) $overheadRate['rate_per_l'];
    $total = round($materials + $overhead, 4);

    $retired = $pdo->prepare("UPDATE app.recipe_versions SET status = 'retired' WHERE product_id = :p AND status = 'active' AND id <> :id RETURNING id, version_no");
    $retired->execute(['p' => $version['product_id'], 'id' => $id]);
    $previous = $retired->fetch() ?: null;

    $activate = $pdo->prepare(<<<'SQL'
        UPDATE app.recipe_versions SET status = 'active', activated_at = now(), activated_by = :by, expected_total_loss_pct = :loss,
               standard_cost_total = :total, standard_cost_per_l = :per_l
        WHERE id = :id AND status = 'draft'
        RETURNING id, product_id, version_no, status, activated_at, expected_total_loss_pct, standard_cost_total, standard_cost_per_l
    SQL);
    $activate->execute(['by' => $actorId, 'loss' => $totalLoss, 'total' => $total, 'per_l' => round($total / $volumeL, 6), 'id' => $id]);
    $after = $activate->fetch();
    $after['materials_cost'] = round($materials, 4);
    $after['overhead_cost'] = round($overhead, 4);
    $after['overhead_included'] = $overheadRate !== null;
    $after['overhead_rate_per_l'] = $overheadRate['rate_per_l'] ?? null;
    $after['previous_active'] = $previous;

    $productStatus = $pdo->prepare("UPDATE app.products SET status = 'active' WHERE id = :p AND status = 'draft' RETURNING id");
    $productStatus->execute(['p' => $version['product_id']]);
    $after['product_activated'] = $productStatus->fetch() !== false;
    return $after;
}

/** Lines with quantities at the recipe's target volume and at $volumeL. Per-volume lines scale; fixed per-batch lines do not. */
function scale_recipe_lines(PDO $pdo, int $id, float $volumeL): array
{
    $version = find_recipe_version($pdo, $id);
    if ($version === null) {
        return [];
    }
    $lines = [];
    foreach ($version['lines'] as $line) {
        $lines[] = $line + recipe_scale_line($line, (float) $version['target_batch_volume_l'], $volumeL);
    }
    return $lines;
}

/** Matches lines and stages of two versions. Compares $other against $current: added = in current only, removed = in other only. */
function diff_recipe_versions(PDO $pdo, int $currentId, int $otherId): array
{
    $current = find_recipe_version($pdo, $currentId);
    $other = find_recipe_version($pdo, $otherId);
    if ($current === null || $other === null) {
        return ['lines' => ['added' => [], 'removed' => [], 'changed' => []], 'stages' => ['added' => [], 'removed' => [], 'changed' => []], 'current' => $current, 'other' => $other];
    }
    $keyed = static function (array $rows, callable $key): array {
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $k = $key($row);
            $seen[$k] = ($seen[$k] ?? 0) + 1;
            $out[$k . '#' . $seen[$k]] = $row;
        }
        return $out;
    };
    $compare = static function (array $a, array $b, array $fields): array {
        $changes = [];
        foreach ($fields as $field) {
            $x = $a[$field] ?? null;
            $y = $b[$field] ?? null;
            $same = is_numeric($x) && is_numeric($y) ? abs((float) $x - (float) $y) < 0.0000005 : (string) $x === (string) $y;
            if (!$same) {
                $changes[$field] = ['from' => $y, 'to' => $x];
            }
        }
        return $changes;
    };
    $diffSet = static function (array $currentRows, array $otherRows, callable $key, array $fields) use ($keyed, $compare): array {
        $c = $keyed($currentRows, $key);
        $o = $keyed($otherRows, $key);
        $result = ['added' => [], 'removed' => [], 'changed' => []];
        foreach ($c as $k => $row) {
            if (!isset($o[$k])) {
                $result['added'][] = $row;
            } elseif (($changes = $compare($row, $o[$k], $fields)) !== []) {
                $result['changed'][] = ['row' => $row, 'other' => $o[$k], 'changes' => $changes];
            }
        }
        foreach ($o as $k => $row) {
            if (!isset($c[$k])) {
                $result['removed'][] = $row;
            }
        }
        return $result;
    };
    return [
        'current' => $current, 'other' => $other,
        'lines' => $diffSet($current['lines'], $other['lines'], static fn(array $l) => $l['item_id'] . '|' . $l['stage_code'] . '|' . $l['purpose'], ['qty_per_batch_base', 'qty_per_l', 'consumption_mode']),
        'stages' => $diffSet($current['stages'], $other['stages'], static fn(array $s) => $s['stage_code'], ['seq', 'expected_loss_pct', 'expected_duration_days', 'instructions']),
    ];
}

/** All stage codes as code => name (labels for the line editor's stage select). */
function recipe_stage_names(PDO $pdo): array
{
    return array_column($pdo->query('SELECT code, name FROM app.stages ORDER BY display_order')->fetchAll(), 'name', 'code');
}

/** The data the recipe read view needs (scaled at the version's own volume). */
function recipe_view_data(PDO $pdo, array $version, array $user, ?float $scaleL = null): array
{
    $scaleL ??= (float) $version['target_batch_volume_l'];
    return [
        'version' => $version, 'lines' => scale_recipe_lines($pdo, (int) $version['id'], $scaleL), 'scaleL' => $scaleL,
        'scaleDisplay' => round((float) to_display($scaleL, 'L'), 4), 'user' => $user,
        'otherVersions' => recipe_version_options($pdo, (int) $version['product_id'], (int) $version['id']),
        'overheadRate' => recipe_overhead_rate($pdo, $version['beverage_type']), 'alert' => null,
    ];
}
