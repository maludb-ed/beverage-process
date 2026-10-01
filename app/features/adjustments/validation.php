<?php
declare(strict_types=1);

/**
 * Validate adjustment line rows. Quantities arrive signed in display units and leave in base units.
 * Negative lines may not exceed the lot's on-hand at the location unless the location allows negative stock.
 * Returns [cleanLines, errorsByRow]. Blank rows are skipped.
 */
function validate_adjustment_lines(PDO $pdo, array $rawLines, int $locationId, bool $allowNegative): array
{
    $lines = [];
    $errors = [];
    $itemIds = [];
    foreach ($rawLines as $raw) {
        if (is_array($raw)) { $itemIds[] = (int) ($raw['item_id'] ?? 0); }
    }
    $facts = inventory_item_facts($pdo, $itemIds);
    $removed = [];   // lot id => base qty already taken by earlier rows
    foreach ($rawLines as $n => $raw) {
        if (!is_array($raw)) {
            continue;
        }
        $itemId = (int) ($raw['item_id'] ?? 0);
        $lotId = (int) ($raw['lot_id'] ?? 0);
        $qtyRaw = trim(str_replace(',', '', (string) ($raw['qty_delta'] ?? '')));
        $costRaw = trim(str_replace([',', '$'], '', (string) ($raw['unit_cost'] ?? '')));
        if ($itemId === 0 && $lotId === 0 && $qtyRaw === '') {
            continue;
        }
        $rowErrors = [];
        $item = $facts[$itemId] ?? null;
        if ($item === null) { $rowErrors['item_id'] = 'Choose an item.'; }
        if ($item !== null) {
            $lotItem = $lotId > 0 ? $pdo->prepare('SELECT item_id, lot_number FROM app.lots WHERE id = :id') : null;
            $lot = null;
            if ($lotItem !== null) {
                $lotItem->execute(['id' => $lotId]);
                $lot = $lotItem->fetch() ?: null;
            }
            if ($lot === null || (int) $lot['item_id'] !== $itemId) { $rowErrors['lot_id'] = 'Choose a lot of this item.'; }
        }
        $qtyBase = null;
        if ($qtyRaw === '' || !is_numeric($qtyRaw) || (float) $qtyRaw == 0.0) {
            $rowErrors['qty_delta'] = 'Enter a quantity change other than zero.';
        } elseif ($item !== null) {
            $qtyBase = round((float) from_display((float) $qtyRaw, $item['base_unit_code'], $item['kind']), 4);
            if (abs($qtyBase) < 0.0001) {
                $rowErrors['qty_delta'] = 'That quantity is too small.';
            } elseif ($qtyBase < 0 && !isset($rowErrors['lot_id']) && !$allowNegative) {
                $onHand = lot_on_hand($pdo, $lotId, $locationId) - ($removed[$lotId] ?? 0.0);
                if (-$qtyBase > round($onHand, 4) + 0.00005) {
                    $rowErrors['qty_delta'] = 'Only ' . fmt_qty(max(0.0, $onHand), $item['base_unit_code'], 2, $item['kind']) . ' of ' . $lot['lot_number'] . ' is on hand here, so it cannot go that far negative.';
                } else {
                    $removed[$lotId] = ($removed[$lotId] ?? 0.0) - $qtyBase;
                }
            }
        }
        $cost = null;
        if ($costRaw !== '') {
            if (!is_numeric($costRaw) || (float) $costRaw < 0) { $rowErrors['unit_cost'] = 'Enter a cost of zero or more.'; } else { $cost = (float) $costRaw; }
        }
        if ($rowErrors !== []) {
            $errors[$n] = $rowErrors;
        }
        $lines[$n] = ['item_id' => $itemId ?: null, 'lot_id' => $lotId ?: null, 'qty_delta' => $qtyRaw, 'qty_delta_base' => $qtyBase, 'unit_cost' => $costRaw, 'unit_cost_base' => $cost,
            'note' => trim((string) ($raw['note'] ?? '')) ?: null];
    }
    return [$lines, $errors];
}
