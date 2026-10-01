<?php
declare(strict_types=1);

/** Quantity at the recipe's target volume and at $scaleL. Per-volume lines scale with volume; fixed per-batch lines stay put. */
function recipe_scale_line(array $line, float $targetL, float $scaleL): array
{
    if ($line['qty_per_batch_base'] !== null) {
        $qty = (float) $line['qty_per_batch_base'];
        return ['qty_at_target' => $qty, 'qty_scaled' => $qty, 'scales' => false];
    }
    $perL = (float) $line['qty_per_l'];
    return ['qty_at_target' => $perL * $targetL, 'qty_scaled' => $perL * $scaleL, 'scales' => true];
}

/** Display unit kind for an item class (fruit weights use the fruit unit). */
function recipe_unit_kind(string $itemClass): string
{
    return $itemClass === 'fruit' ? 'fruit' : 'default';
}

/** A line quantity entered in display units to storage: ['qty_per_batch_base' => ..., 'qty_per_l' => ...], one of them null. */
function recipe_qty_to_base(float $entered, string $basis, string $baseUnit, string $itemClass): array
{
    $base = (float) from_display($entered, $baseUnit, recipe_unit_kind($itemClass));
    if ($basis === 'per_volume') {
        // Entered per display volume unit (e.g. per gallon of batch); stored per liter of batch.
        return ['qty_per_batch_base' => null, 'qty_per_l' => $base / (float) from_display(1, 'L')];
    }
    return ['qty_per_batch_base' => $base, 'qty_per_l' => null];
}

/** Storage back to the display number shown in the editor and the unit label. */
function recipe_qty_to_display(array $line): array
{
    $kind = recipe_unit_kind((string) $line['item_class']);
    $unit = display_unit($line['base_unit_code'], $kind);
    if ($line['qty_per_l'] !== null) {
        return ['basis' => 'per_volume', 'qty' => round((float) to_display($line['qty_per_l'], $line['base_unit_code'], $kind) * unit_factor(display_unit('L')), 6), 'unit' => $unit];
    }
    return ['basis' => 'per_batch', 'qty' => round((float) to_display($line['qty_per_batch_base'], $line['base_unit_code'], $kind), 6), 'unit' => $unit];
}

/** Unit label for a line quantity input: "lb" per batch, "lb / gal" per volume. */
function recipe_qty_unit_label(?array $item, string $basis): string
{
    if ($item === null) {
        return '';
    }
    $unit = display_unit($item['base_unit_code'], recipe_unit_kind((string) $item['item_class']));
    return $basis === 'per_volume' ? $unit . ' / ' . display_unit('L') : $unit;
}

/** Expected total loss across stages: 100 x (1 - product of (1 - loss/100)). */
function recipe_total_loss_pct(array $stages): float
{
    $keep = 1.0;
    foreach ($stages as $stage) {
        $keep *= 1 - (float) $stage['expected_loss_pct'] / 100;
    }
    return round(100 * (1 - $keep), 2);
}

/**
 * Validate the repeating stage rows. Pure. Returns [cleanRows, errorsByRow].
 * Blank rows (no stage and no sequence) are skipped. $stageOptions: code => name allowed for the beverage.
 */
function validate_recipe_stages(array $rawStages, array $stageOptions): array
{
    $rows = [];
    $errors = [];
    $seqs = [];
    foreach ($rawStages as $n => $raw) {
        if (!is_array($raw)) {
            continue;
        }
        $code = trim((string) ($raw['stage_code'] ?? ''));
        $seqRaw = trim((string) ($raw['seq'] ?? ''));
        if ($code === '' && $seqRaw === '') {
            continue;
        }
        $rowErrors = [];
        if (!ctype_digit($seqRaw) || (int) $seqRaw < 1) {
            $rowErrors['seq'] = 'Whole number, 1 or more.';
        } elseif (isset($seqs[(int) $seqRaw])) {
            $rowErrors['seq'] = 'Sequence already used.';
        } else {
            $seqs[(int) $seqRaw] = true;
        }
        if (!isset($stageOptions[$code])) {
            $rowErrors['stage_code'] = 'Choose a stage.';
        }
        $lossRaw = str_replace(',', '', trim((string) ($raw['loss'] ?? '')));
        $loss = $lossRaw === '' ? 0.0 : (is_numeric($lossRaw) ? (float) $lossRaw : null);
        if ($loss === null || $loss < 0 || $loss > 100) {
            $rowErrors['loss'] = 'Between 0 and 100.';
        }
        $daysRaw = trim((string) ($raw['days'] ?? ''));
        $days = null;
        if ($daysRaw !== '') {
            if (!ctype_digit($daysRaw)) { $rowErrors['days'] = 'Whole days, 0 or more.'; } else { $days = (int) $daysRaw; }
        }
        if ($rowErrors !== []) {
            $errors[$n] = $rowErrors;
        }
        $rows[$n] = [
            'seq' => ctype_digit($seqRaw) ? (int) $seqRaw : $seqRaw, 'stage_code' => $code, 'expected_loss_pct' => $loss ?? $lossRaw,
            'expected_duration_days' => $days ?? ($daysRaw === '' ? null : $daysRaw), 'instructions' => mb_substr(trim((string) ($raw['instructions'] ?? '')), 0, 1000) ?: null,
        ];
    }
    return [$rows, $errors];
}

/**
 * Validate the repeating recipe line rows. Pure. Returns [cleanRows, errorsByRow].
 * $catalog: item id => item row; $stageCodes: codes of the stages being saved with the version.
 * Quantities are entered in display units and returned as storage values.
 */
function validate_recipe_lines(array $rawLines, array $catalog, array $stageCodes): array
{
    $rows = [];
    $errors = [];
    $seqs = [];
    foreach ($rawLines as $n => $raw) {
        if (!is_array($raw)) {
            continue;
        }
        $itemId = (int) ($raw['item_id'] ?? 0);
        $qtyRaw = str_replace(',', '', trim((string) ($raw['qty'] ?? '')));
        $seqRaw = trim((string) ($raw['seq'] ?? ''));
        if ($itemId === 0 && $qtyRaw === '') {
            continue;
        }
        $rowErrors = [];
        $item = $catalog[$itemId] ?? null;
        if ($item === null) { $rowErrors['item'] = 'Choose an item.'; }
        if (!ctype_digit($seqRaw) || (int) $seqRaw < 1) {
            $rowErrors['seq'] = 'Whole number, 1 or more.';
        } elseif (isset($seqs[(int) $seqRaw])) {
            $rowErrors['seq'] = 'Sequence already used.';
        } else {
            $seqs[(int) $seqRaw] = true;
        }
        $stage = trim((string) ($raw['stage_code'] ?? ''));
        if (!in_array($stage, $stageCodes, true)) { $rowErrors['stage'] = 'Choose one of this version\'s stages.'; }
        $purpose = (string) ($raw['purpose'] ?? '');
        if (!isset(RECIPE_PURPOSES[$purpose])) { $rowErrors['purpose'] = 'Choose a purpose.'; }
        $basis = (string) ($raw['basis'] ?? '');
        if (!isset(RECIPE_BASES[$basis])) { $rowErrors['basis'] = 'Choose a basis.'; }
        $qty = is_numeric($qtyRaw) ? (float) $qtyRaw : null;
        if ($qty === null || $qty <= 0) { $rowErrors['qty'] = 'Enter a quantity above zero.'; }
        $mode = (string) ($raw['mode'] ?? '');
        if ($mode === '' && $item !== null) { $mode = (string) $item['consumption_mode']; }
        if (!isset(RECIPE_MODES[$mode])) { $rowErrors['mode'] = 'Choose a consumption mode.'; }
        if ($rowErrors !== []) {
            $errors[$n] = $rowErrors;
        }
        $base = ['qty_per_batch_base' => null, 'qty_per_l' => null];
        if ($item !== null && $qty !== null && $qty > 0 && isset(RECIPE_BASES[$basis])) {
            $base = recipe_qty_to_base($qty, $basis, $item['base_unit_code'], $item['item_class']);
        }
        $rows[$n] = [
            'seq' => ctype_digit($seqRaw) ? (int) $seqRaw : $seqRaw, 'item_id' => $itemId ?: null, 'stage_code' => $stage, 'purpose' => $purpose, 'basis' => $basis,
            'qty' => $qty ?? $qtyRaw, 'consumption_mode' => $mode, 'notes' => mb_substr(trim((string) ($raw['notes'] ?? '')), 0, 500) ?: null,
        ] + $base;
    }
    return [$rows, $errors];
}

/** The recipe quantity of a line as text in display units: "1.1023 lb" or "0.0044 lb / gal". */
function recipe_line_qty_text(array $line): string
{
    $d = recipe_qty_to_display($line);
    $text = rtrim(rtrim(number_format($d['qty'], 4), '0'), '.');
    return ($text === '' ? '0' : $text) . ' ' . ($d['basis'] === 'per_volume' ? $d['unit'] . ' / ' . display_unit('L') : $d['unit']);
}
