<?php
declare(strict_types=1);

require_once __DIR__ . '/../batches/validation.php';

/**
 * Validate press-run input rows (lot_id, qty_lb). Pure. Returns [clean rows keyed n with
 * lot_id, qty_kg, qty_lb; errors by row]. Blank rows are skipped.
 */
function press_run_validate_inputs(array $raw, array $fruitLots): array
{
    $rows = [];
    $errors = [];
    $taken = [];
    foreach ($raw as $n => $row) {
        if (!is_array($row)) {
            continue;
        }
        $lotId = (int) ($row['lot_id'] ?? 0);
        $qty = batches_num($row['qty_lb'] ?? '');
        if ($lotId === 0 && $qty === null) {
            continue;
        }
        $rowErrors = [];
        $lot = $fruitLots[$lotId] ?? null;
        if ($lot === null) {
            $rowErrors['lot_id'] = 'Choose a released fruit lot with stock.';
        } elseif (isset($taken[$lotId])) {
            $rowErrors['lot_id'] = 'This lot is already on another row.';
        }
        $kg = is_float($qty) ? round((float) from_display($qty, 'kg', 'fruit'), 3) : null;
        if ($qty === false || $qty === null || $qty <= 0) {
            $rowErrors['qty_lb'] = 'Enter the weight pressed.';
        } elseif ($lot !== null && $kg > (float) $lot['on_hand'] + 0.0005) {
            $rowErrors['qty_lb'] = 'Only ' . fmt_qty($lot['on_hand'], 'kg', 1, 'fruit') . ' of this lot is on hand.';
        }
        $taken[$lotId] = true;
        if ($rowErrors !== []) {
            $errors[$n] = $rowErrors;
        }
        $rows[$n] = ['lot_id' => $lotId ?: null, 'qty_lb' => is_float($qty) ? $qty : ($row['qty_lb'] ?? ''), 'qty_kg' => $kg];
    }
    return [$rows, $errors];
}

/**
 * Validate press-run output rows (kind, item_id, qty, brix, vessel_id, location_id). Pure.
 * Juice qty is in the volume display unit, pomace in the item's display unit. Returns
 * [clean rows with qty_base, errors by row, capacity warnings].
 */
function press_run_validate_outputs(array $raw, array $outputItems, array $vessels, array $locations): array
{
    $rows = [];
    $errors = [];
    $warnings = [];
    $juiceVessels = [];
    foreach ($raw as $n => $row) {
        if (!is_array($row)) {
            continue;
        }
        $kind = (string) ($row['kind'] ?? 'juice');
        $kind = isset(PRESS_RUN_OUTPUT_KINDS[$kind]) ? $kind : 'juice';
        $itemId = (int) ($row['item_id'] ?? 0);
        $qty = batches_num($row['qty'] ?? '');
        if ($itemId === 0 && $qty === null) {
            continue;
        }
        $rowErrors = [];
        $item = $outputItems[$kind][$itemId] ?? null;
        if ($item === null) {
            $rowErrors['item_id'] = $kind === 'juice' ? 'Choose a juice item.' : 'Choose a co-product item.';
        }
        if ($qty === false || $qty === null || $qty <= 0) {
            $rowErrors['qty'] = 'Enter a quantity above zero.';
        }
        $qtyBase = is_float($qty) && $item !== null ? round((float) from_display($qty, $item['base_unit_code']), 3) : null;
        $brix = batches_num($row['brix'] ?? '');
        $vesselId = (int) ($row['vessel_id'] ?? 0);
        $locationId = (int) ($row['location_id'] ?? 0);
        if ($kind === 'juice') {
            if ($brix === false || (is_float($brix) && ($brix < 0 || $brix > 40))) {
                $rowErrors['brix'] = 'Brix should be between 0 and 40.';
            }
            $vessel = $vessels[$vesselId] ?? null;
            if ($vessel === null) {
                $rowErrors['vessel_id'] = 'Choose the vessel the juice goes into.';
            } elseif (isset($juiceVessels[$vesselId])) {
                $rowErrors['vessel_id'] = 'Each juice output needs its own vessel.';
            } elseif ($qtyBase !== null && ($warning = batches_capacity_warning($vessel, $qtyBase)) !== null) {
                $warnings[] = $warning;
            }
            $juiceVessels[$vesselId] = true;
        } elseif (!isset($locations[$locationId])) {
            $rowErrors['location_id'] = 'Choose where the pomace goes.';
        }
        if ($rowErrors !== []) {
            $errors[$n] = $rowErrors;
        }
        $rows[$n] = [
            'kind' => $kind, 'item_id' => $itemId ?: null, 'qty' => is_float($qty) ? $qty : ($row['qty'] ?? ''), 'qty_base' => $qtyBase,
            'brix' => $kind === 'juice' && is_float($brix) ? $brix : null, 'vessel_id' => $kind === 'juice' ? ($vesselId ?: null) : null,
            'location_id' => $kind === 'pomace' ? ($locationId ?: null) : null,
        ];
    }
    return [$rows, $errors, $warnings];
}
